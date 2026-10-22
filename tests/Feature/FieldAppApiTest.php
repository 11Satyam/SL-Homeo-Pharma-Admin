<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Support\BusinessDay;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FieldAppApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Distributor $distA;

    private Location $locA;

    private User $staffA;

    private Shop $shopA;

    private Distributor $distB;

    private User $staffB;

    private Shop $shopB;

    private Product $p1;

    private Product $p2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->asAdmin();

        $this->p1 = Product::create(['name' => 'Paracetamol 500', 'sku' => 'PCM500', 'category' => 'Analgesic', 'unit' => 'strip', 'selling_price' => 100, 'tax_rate' => 12]);
        $this->p2 = Product::create(['name' => 'Cough Syrup', 'sku' => 'CS100', 'category' => 'Syrup', 'unit' => 'bottle', 'selling_price' => 50, 'tax_rate' => 0]);

        [$this->distA, $this->locA, $this->staffA, $this->shopA] = $this->distributor('Alpha');
        [$this->distB, $locB, $this->staffB, $this->shopB] = $this->distributor('Beta');

        $stock = app(StockService::class);
        $stock->move($this->p1->id, Location::system('external'), $this->locA, 50, 'opening', 'open-a1');
        $stock->move($this->p2->id, Location::system('external'), $this->locA, 5, 'opening', 'open-a2');
        $stock->move($this->p1->id, Location::system('external'), $locB, 10, 'opening', 'open-b1');
    }

    // ---------------- helpers ----------------

    private function distributor(string $name): array
    {
        $d = Distributor::create(['business_name' => $name.' Pharma', 'area' => $name.' Area']);
        $loc = Location::create(['name' => $name, 'type' => 'distributor', 'distributor_id' => $d->id]);
        $staff = User::create(['name' => $name.' Staff', 'email' => strtolower($name).'.staff@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);
        $shop = Shop::create(['name' => $name.' Medicals', 'area' => 'Market', 'distributor_id' => $d->id]);

        return [$d, $loc, $staff, $shop];
    }

    private function token(User $u, array $abilities = ['field-app'], $expires = null): string
    {
        return $u->createToken('test', $abilities, $expires ?? now()->addDay())->plainTextToken;
    }

    /** Fresh guards per request so token revocation is observed as a real client would see it. */
    private function api(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        config(['auth.defaults.guard' => 'web']);
        $headers += ['Accept' => 'application/json'];
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->json($method, '/api/app'.$uri, $data, $headers);
    }

    private function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    private function asAdmin(): void
    {
        $this->flushHeaders(); // browser requests, not API defaults left by withHeaders()
        $this->app['auth']->forgetGuards();
        config(['auth.defaults.guard' => 'web']);
        $this->actingAs($this->admin, 'web');
    }

    private function balance(Product $p): StockBalance
    {
        return StockBalance::where('location_id', $this->locA->id)->where('product_id', $p->id)->first();
    }

    private function placeOrder(string $token, array $items, array $headers = []): TestResponse
    {
        return $this->api('POST', '/orders', ['shop_id' => $this->shopA->id, 'items' => $items], $token, $headers ?: $this->key());
    }

    // ---------------- login / session ----------------

    public function test_login_returns_token_profile_and_no_secrets(): void
    {
        $r = $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'secret-password', 'device_name' => 'Pixel 8'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'staff')
            ->assertJsonPath('data.distributor.id', $this->distA->id)
            ->assertJsonPath('data.distributor.name', 'Alpha Pharma');
        $this->assertMatchesRegularExpression('/^\d+\|slp_\w{40,}$/', $r->json('data.token'));
        $this->assertContains('orders.create', $r->json('data.permissions'));
        $this->assertStringNotContainsString('password', strtolower($r->getContent()));
        $this->assertStringNotContainsString('remember_token', $r->getContent());

        $this->api('GET', '/session', [], $r->json('data.token'))->assertOk()
            ->assertJsonPath('data.user.id', $this->staffA->id)
            ->assertJsonStructure(['data' => ['token_expires_at', 'config' => ['payment_methods', 'expense_categories', 'geofence']]]);
    }

    public function test_login_rejects_bad_credentials_wrong_role_and_inactive_accounts(): void
    {
        $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'wrong-password'])
            ->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
        $this->api('POST', '/auth/login', ['email' => 'nobody@x.test', 'password' => 'secret-password'])
            ->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
        $this->api('POST', '/auth/login', ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $this->api('POST', '/auth/login', ['email' => 'admin@x.test', 'password' => 'secret-password'])
            ->assertStatus(403)->assertJsonPath('code', 'role_not_permitted');

        $this->staffA->update(['status' => 'suspended']);
        $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'secret-password'])
            ->assertStatus(403)->assertJsonPath('code', 'account_inactive');

        $this->distB->update(['status' => 'inactive']);
        $this->api('POST', '/auth/login', ['email' => 'beta.staff@x.test', 'password' => 'secret-password'])
            ->assertStatus(403)->assertJsonPath('code', 'account_inactive');
        $this->assertSame(0, $this->staffA->tokens()->count() + $this->staffB->tokens()->count());
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'bad-password'])->assertStatus(401);
        }
        $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'secret-password'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts')->assertHeader('Retry-After');
    }

    public function test_missing_invalid_expired_revoked_and_foreign_tokens_are_rejected(): void
    {
        $this->api('GET', '/session')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
        $this->api('GET', '/session', [], 'garbage-token')->assertStatus(401);
        $this->api('GET', '/session', [], $this->token($this->staffA, ['field-app'], now()->subMinute()))->assertStatus(401);
        // A token minted for some other purpose cannot drive the field app.
        $this->api('GET', '/dashboard', [], $this->token($this->staffA, ['reports']))->assertStatus(401)->assertJsonPath('code', 'invalid_token');

        $t = $this->api('POST', '/auth/login', ['email' => 'alpha.staff@x.test', 'password' => 'secret-password'])->json('data.token');
        $this->api('POST', '/auth/logout', [], $t)->assertOk();
        $this->api('GET', '/session', [], $t)->assertStatus(401);

        // Suspending a user in the Admin Panel locks the app out at the next request and revokes tokens.
        $t = $this->token($this->staffA);
        $this->staffA->update(['status' => 'suspended']);
        $this->api('GET', '/dashboard', [], $t)->assertStatus(403)->assertJsonPath('code', 'account_inactive');
        $this->assertSame(0, $this->staffA->tokens()->count());

        // Admin/distributor tokens with the right ability still fail the role check.
        $this->api('GET', '/session', [], $this->token($this->admin))->assertStatus(403);
    }

    public function test_admin_status_change_revokes_tokens(): void
    {
        $this->token($this->staffA);
        $distUser = User::create(['name' => 'Owner', 'email' => 'owner@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $this->distA->id]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($distUser, 'web')->post(route('erp.users.status', $this->staffA), ['status' => 'suspended'])->assertRedirect();
        $this->assertSame(0, $this->staffA->tokens()->count());
    }

    public function test_refresh_rotates_token(): void
    {
        $old = $this->token($this->staffA);
        $new = $this->api('POST', '/auth/refresh', [], $old)->assertOk()->json('data.token');
        $this->assertNotSame($old, $new);
        $this->api('GET', '/session', [], $old)->assertStatus(401);
        $this->api('GET', '/session', [], $new)->assertOk();
    }

    public function test_forgot_password_does_not_reveal_accounts(): void
    {
        Notification::fake();
        $a = $this->api('POST', '/auth/forgot-password', ['email' => 'alpha.staff@x.test'])->assertOk()->json('message');
        $b = $this->api('POST', '/auth/forgot-password', ['email' => 'unknown@x.test'])->assertOk()->json('message');
        $this->assertSame($a, $b);
        Notification::assertSentTo($this->staffA, ResetPassword::class);
    }

    // ---------------- products / parties ----------------

    public function test_products_search_filter_stock_and_detail(): void
    {
        $t = $this->token($this->staffA);
        Product::create(['name' => 'Discontinued', 'sku' => 'OLD1', 'selling_price' => 1, 'active' => false]);

        $r = $this->api('GET', '/products', [], $t)->assertOk();
        $this->assertSame(['CS100', 'PCM500'], collect($r->json('data'))->pluck('sku')->all(), 'inactive excluded');
        $this->assertSame(['Analgesic', 'Syrup'], $r->json('filters.categories'));
        $r->assertJsonPath('meta.total', 2);

        $this->api('GET', '/products?search=pcm', [], $t)->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'PCM500')
            ->assertJsonPath('data.0.available_qty', 50)->assertJsonPath('data.0.price_incl_tax', '112.00')
            ->assertJsonPath('data.0.tracking', 'quantity');
        $this->api('GET', '/products?search=%25', [], $t)->assertJsonCount(0, 'data'); // LIKE wildcards are literal
        $this->api('GET', '/products?category=Syrup', [], $t)->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'CS100');
        $this->api('GET', '/products?per_page=1', [], $t)->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
        $this->api('GET', '/products?per_page=500', [], $t)->assertStatus(422);

        // Availability is per distributor and nets out reservations.
        $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 8]])->assertCreated();
        $this->api('GET', '/products/'.$this->p1->id, [], $t)->assertOk()->assertJsonPath('data.available_qty', 42);
        $this->api('GET', '/products/'.$this->p1->id, [], $this->token($this->staffB))->assertJsonPath('data.available_qty', 10);
        $this->api('GET', '/products?in_stock=1', [], $this->token($this->staffB))->assertJsonCount(1, 'data');

        $old = Product::where('sku', 'OLD1')->first();
        $this->api('GET', '/products/'.$old->id, [], $t)->assertStatus(404)->assertJsonPath('message', 'Resource not found.');
    }

    public function test_parties_are_scoped_to_distributor(): void
    {
        Shop::create(['name' => 'Closed Shop', 'distributor_id' => $this->distA->id, 'status' => 'inactive']);
        $r = $this->api('GET', '/parties', [], $this->token($this->staffA))->assertOk();
        $this->assertSame(['Alpha Medicals'], collect($r->json('data'))->pluck('name')->all());
        $this->api('GET', '/parties?search=Beta', [], $this->token($this->staffA))->assertJsonCount(0, 'data');
    }

    // ---------------- orders ----------------

    public function test_order_is_priced_on_server_and_reserves_without_deducting(): void
    {
        $t = $this->token($this->staffA);
        $r = $this->api('POST', '/orders', [
            'shop_id' => $this->shopA->id, 'remarks' => 'Deliver before noon',
            'items' => [
                ['product_id' => $this->p1->id, 'qty' => 2, 'price' => 1, 'discount' => 99],
                ['product_id' => $this->p2->id, 'qty' => 1],
                ['product_id' => $this->p1->id, 'qty' => 1],
            ],
            'total' => 1, 'staff_id' => $this->staffB->id, 'distributor_id' => $this->distB->id,
        ], $t, $this->key())->assertCreated()->assertHeader('Idempotent-Replayed', 'false');

        // 3 x 100 + 12% = 336.00, 1 x 50 = 50.00
        $r->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.subtotal', '350.00')->assertJsonPath('data.tax_total', '36.00')
            ->assertJsonPath('data.discount_total', '0.00')->assertJsonPath('data.total', '386.00')
            ->assertJsonPath('data.items.0.qty', 3)->assertJsonPath('data.items.0.price', '100.00')
            ->assertJsonPath('data.remarks', 'Deliver before noon')
            ->assertJsonPath('data.staff.id', $this->staffA->id);

        $o = Order::findOrFail($r->json('data.id'));
        $this->assertSame([$this->distA->id, $this->staffA->id, $this->staffA->id, '386.00'], [$o->distributor_id, $o->staff_id, $o->created_by, number_format((float) $o->total, 2, '.', '')]);
        $this->assertSame(50, (int) $this->balance($this->p1)->qty, 'creating an order must not deduct stock');
        $this->assertSame(3, (int) $this->balance($this->p1)->reserved);

        $this->api('GET', '/orders', [], $t)->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', $o->number);
        $this->api('GET', '/orders/'.$o->id, [], $t)->assertOk()->assertJsonPath('data.total', '386.00');

        // Delivery (Admin Panel) posts the deduction exactly once.
        $this->asAdmin();
        app(SalesService::class)->deliver($o, $o->lines->mapWithKeys(fn ($l) => [$l->id => $l->qty])->all());
        $this->assertSame(47, (int) $this->balance($this->p1)->qty);
        $this->assertSame(0, (int) $this->balance($this->p1)->reserved);
        $this->assertSame([], app(StockService::class)->reconcile());
    }

    public function test_order_validation_and_stock_limits(): void
    {
        $t = $this->token($this->staffA);
        $this->api('POST', '/orders', ['shop_id' => $this->shopA->id, 'items' => [['product_id' => $this->p1->id, 'qty' => 1]]], $t)
            ->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
        $this->placeOrder($t, [])->assertStatus(422)->assertJsonValidationErrors('items');
        $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 0]])->assertStatus(422)->assertJsonValidationErrors('items.0.qty');
        $inactive = Product::create(['name' => 'Old', 'sku' => 'OLD2', 'selling_price' => 5, 'active' => false]);
        $this->placeOrder($t, [['product_id' => $inactive->id, 'qty' => 1]])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');

        $this->placeOrder($t, [['product_id' => $this->p2->id, 'qty' => 6]])
            ->assertStatus(422)->assertJsonPath('message', 'Insufficient available stock for Cough Syrup.');
        $this->assertSame(0, Order::count(), 'failed order leaves no partial records');
        $this->assertSame(0, (int) $this->balance($this->p2)->reserved);
    }

    public function test_retried_order_is_created_once(): void
    {
        $t = $this->token($this->staffA);
        $headers = $this->key();
        $first = $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 4]], $headers)->assertCreated();
        $again = $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 4]], $headers)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Order::count());
        $this->assertSame(4, (int) $this->balance($this->p1)->reserved, 'reserved once');
        // Keys are per user: another staff member's identical key is independent.
        $this->api('POST', '/orders', ['shop_id' => $this->shopB->id, 'items' => [['product_id' => $this->p1->id, 'qty' => 1]]], $this->token($this->staffB), $headers)->assertCreated();
    }

    // ---------------- attendance ----------------

    public function test_attendance_check_in_out_sequence_and_validation(): void
    {
        $t = $this->token($this->staffA);
        $this->api('GET', '/attendance/today', [], $t)->assertOk()->assertJsonPath('data.status', 'not_checked_in')->assertJsonPath('data.record', null);
        $this->api('POST', '/attendance/check-out', ['latitude' => 19.07, 'longitude' => 72.87], $t)
            ->assertStatus(422)->assertJsonPath('message', 'You have not checked in today.');

        $this->api('POST', '/attendance/check-in', ['latitude' => 91, 'longitude' => 72.87], $t)->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->api('POST', '/attendance/check-in', ['latitude' => 19.07, 'longitude' => -181], $t)->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->api('POST', '/attendance/check-in', ['latitude' => 'abc', 'longitude' => 72.87], $t)->assertStatus(422);
        $this->api('POST', '/attendance/check-in', ['longitude' => 72.87], $t)->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->api('POST', '/attendance/check-in', ['latitude' => 0, 'longitude' => 0], $t)->assertStatus(422)->assertJsonValidationErrors('latitude');

        $clientTime = now()->subHours(5)->toIso8601String(); // device clock is wrong; server time wins
        $in = $this->api('POST', '/attendance/check-in', ['latitude' => 19.0760, 'longitude' => 72.8777, 'accuracy_m' => 12.5, 'client_timestamp' => $clientTime], $t)
            ->assertCreated()->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.record.check_in.location.source', 'device_reported')
            ->assertJsonPath('data.record.check_in.location.accuracy_m', 12.5)
            ->assertJsonPath('data.record.check_in.within_geofence', null)
            ->assertJsonPath('data.geofence.configured', false);
        $a = Attendance::firstOrFail();
        $this->assertTrue($a->check_in_at->diffInSeconds(now(), true) < 60);
        $this->assertTrue($a->check_in_client_at->diffInHours(now(), true) >= 4);

        $this->api('POST', '/attendance/check-in', ['latitude' => 19.07, 'longitude' => 72.87], $t)
            ->assertStatus(409)->assertJsonPath('code', 'attendance_conflict')->assertJsonPath('data.status', 'checked_in');
        $this->api('POST', '/attendance/check-out', ['latitude' => 19.08, 'longitude' => 72.88, 'remarks' => 'Route done'], $t)
            ->assertOk()->assertJsonPath('data.status', 'checked_out')->assertJsonPath('data.record.remarks', 'Route done');
        $this->api('POST', '/attendance/check-out', ['latitude' => 19.08, 'longitude' => 72.88], $t)->assertStatus(409);

        $this->api('GET', '/attendance/history', [], $t)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', '/attendance/history', [], $this->token($this->staffB))->assertJsonCount(0, 'data');
        $this->assertSame(1, Attendance::count());
    }

    public function test_geofence_flag_and_enforce_modes(): void
    {
        // Office at Mumbai CST, 200 m radius.
        $this->distA->update(['latitude' => 18.9398, 'longitude' => 72.8355, 'geofence_radius_m' => 200]);
        $t = $this->token($this->staffA);
        $far = ['latitude' => 19.0760, 'longitude' => 72.8777]; // ~15 km away

        config(['fieldapp.attendance.geofence_mode' => 'enforce']);
        $this->api('POST', '/attendance/check-in', $far, $t)->assertStatus(422);
        $this->assertSame(0, Attendance::count());
        $this->api('POST', '/attendance/check-in', ['latitude' => 18.9400, 'longitude' => 72.8357], $t)
            ->assertCreated()->assertJsonPath('data.record.check_in.within_geofence', true)
            ->assertJsonPath('data.geofence.radius_m', 200);

        config(['fieldapp.attendance.geofence_mode' => 'flag']);
        $r = $this->api('POST', '/attendance/check-out', $far, $t)->assertOk()->assertJsonPath('data.record.check_out.within_geofence', false);
        $this->assertGreaterThan(10000, $r->json('data.record.check_out.distance_m'));
    }

    public function test_check_in_can_be_required_before_orders(): void
    {
        config(['fieldapp.attendance.require_check_in' => true]);
        $t = $this->token($this->staffA);
        $this->api('GET', '/dashboard', [], $t)->assertJsonPath('data.quick_actions.new_order.enabled', false);
        $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 1]])->assertStatus(422);
        $this->api('POST', '/attendance/check-in', ['latitude' => 19.07, 'longitude' => 72.87], $t)->assertCreated();
        $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 1]])->assertCreated();
    }

    // ---------------- collections ----------------

    public function test_collection_is_recorded_unverified_and_duplicates_are_blocked(): void
    {
        $t = $this->token($this->staffA);
        $headers = $this->key();
        $body = ['shop_id' => $this->shopA->id, 'amount' => '1500.50', 'method' => 'upi', 'reference' => 'UTR123456', 'remarks' => 'Part payment'];
        $r = $this->api('POST', '/collections', $body, $t, $headers)->assertCreated()
            ->assertJsonPath('data.status', 'collected')->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.amount', '1500.50')->assertJsonPath('data.method', 'upi');
        $this->assertMatchesRegularExpression('/^RC-\d{6}-\d{6}$/', $r->json('data.receipt_number'));

        $this->api('POST', '/collections', $body, $t, $headers)->assertOk()->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $r->json('data.id'));
        $this->api('POST', '/collections', $body + [], $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->api('POST', '/collections', ['reference' => ' utr123456 '] + $body, $t, $this->key())->assertStatus(422);
        $this->assertSame(1, Payment::count());

        $p = Payment::firstOrFail();
        $this->assertSame([$this->staffA->id, $this->distA->id, 'collected', null], [(int) $p->collected_by, (int) $p->distributor_id, $p->status, $p->verified_at]);

        // Validation.
        $this->api('POST', '/collections', ['method' => 'upi', 'reference' => null] + $body, $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('reference');
        $this->api('POST', '/collections', ['amount' => 0] + $body, $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->api('POST', '/collections', ['amount' => '10.555'] + $body, $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->api('POST', '/collections', ['method' => 'bitcoin'] + $body, $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('method');
        $this->api('POST', '/collections', ['collected_at' => now()->addDay()->toIso8601String()] + $body, $t, $this->key())->assertStatus(422)->assertJsonValidationErrors('collected_at');
        // Cash needs no reference.
        $this->api('POST', '/collections', ['shop_id' => $this->shopA->id, 'amount' => 200, 'method' => 'cash'], $t, $this->key())->assertCreated();

        $this->api('GET', '/collections', [], $t)->assertJsonCount(2, 'data');
        $this->api('GET', '/dashboard', [], $t)->assertJsonPath('data.collections_today.amount', '1700.50')
            ->assertJsonPath('data.collections_today.pending_verification', '1700.50');
    }

    public function test_collection_allocates_to_shop_invoice(): void
    {
        $this->asAdmin();
        $sales = app(SalesService::class);
        $o = $sales->createOrder($this->shopA, [$this->p1->id => 5], $this->staffA->id);
        $inv = $sales->deliver($o, [$o->lines[0]->id => 5]); // 560.00

        $t = $this->token($this->staffA);
        $this->api('GET', '/parties/'.$this->shopA->id.'/invoices', [], $t)->assertOk()
            ->assertJsonPath('data.0.number', $inv->number)->assertJsonPath('data.0.outstanding', '560.00')
            ->assertJsonPath('meta.outstanding_total', '560.00');

        $this->api('POST', '/collections', ['shop_id' => $this->shopA->id, 'amount' => 600, 'method' => 'cash',
            'allocations' => [['invoice_id' => $inv->id, 'amount' => 700]]], $t, $this->key())->assertStatus(422);
        $this->api('POST', '/collections', ['shop_id' => $this->shopA->id, 'amount' => 300, 'method' => 'cash',
            'allocations' => [['invoice_id' => $inv->id, 'amount' => 300]]], $t, $this->key())
            ->assertCreated()->assertJsonPath('data.allocations.0.amount', '300.00');
        $this->assertSame('260.00', $inv->fresh()->outstanding());
        $this->assertSame(1, Payment::count(), 'rejected allocation left no payment behind');
    }

    // ---------------- expenses ----------------

    public function test_expense_submission_review_and_policy(): void
    {
        Storage::fake('local');
        $t = $this->token($this->staffA);
        $today = BusinessDay::today();

        $this->api('POST', '/expenses', ['category' => 'fuel', 'amount' => 250, 'expense_date' => now()->addDays(2)->toDateString()], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('expense_date');
        $this->api('POST', '/expenses', ['category' => 'party', 'amount' => 250, 'expense_date' => $today], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('category');
        $this->api('POST', '/expenses', ['category' => 'other', 'amount' => 250, 'expense_date' => $today], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('description');
        $this->api('POST', '/expenses', ['category' => 'fuel', 'amount' => -5, 'expense_date' => $today], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->app['auth']->forgetGuards();
        $r = $this->withHeaders(['Authorization' => 'Bearer '.$t, 'Accept' => 'application/json'] + $this->key())
            ->post('/api/app/expenses', ['category' => 'fuel', 'amount' => '250.00', 'expense_date' => $today, 'description' => 'Bike fuel',
                'receipt' => UploadedFile::fake()->image('bill.jpg')])
            ->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.has_receipt', true);
        $e = Expense::findOrFail($r->json('data.id'));
        Storage::disk('local')->assertExists($e->receipt_path);
        $this->assertSame([$this->staffA->id, $this->distA->id], [(int) $e->user_id, (int) $e->distributor_id]);

        $this->api('GET', '/dashboard', [], $t)->assertJsonPath('data.expenses_today.submitted', '250.00')->assertJsonPath('data.expenses_today.approved', '0.00');

        // Review in Admin Panel; reviewed expenses cannot be reviewed again.
        $this->asAdmin();
        $this->get(route('erp.expenses'))->assertOk()->assertSee('Bike fuel');
        $this->get(route('erp.expenses.receipt', $e))->assertOk();
        $this->post(route('erp.expenses.review', $e), ['decision' => 'rejected'])->assertSessionHasErrors('action');
        $this->post(route('erp.expenses.review', $e), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->post(route('erp.expenses.review', $e), ['decision' => 'rejected', 'note' => 'x'])->assertSessionHasErrors('action');
        $this->assertSame('approved', $e->fresh()->status);

        $this->api('GET', '/expenses?status=approved', [], $t)->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'approved');

        // Auto-approval only when the policy is configured.
        config(['fieldapp.expenses.auto_approve_upto' => 100]);
        $this->api('POST', '/expenses', ['category' => 'food', 'amount' => 80, 'expense_date' => $today], $t, $this->key())->assertJsonPath('data.status', 'approved');
        $this->api('POST', '/expenses', ['category' => 'food', 'amount' => 120, 'expense_date' => $today], $t, $this->key())->assertJsonPath('data.status', 'submitted');

        // Replay returns the original and stores no second receipt.
        $h = $this->key();
        $this->api('POST', '/expenses', ['category' => 'phone', 'amount' => 99, 'expense_date' => $today], $t, $h)->assertCreated();
        $this->api('POST', '/expenses', ['category' => 'phone', 'amount' => 99, 'expense_date' => $today], $t, $h)->assertOk();
        $this->assertSame(4, Expense::count());
    }

    public function test_staff_cannot_review_expenses(): void
    {
        $this->app['auth']->forgetGuards();
        $e = Expense::create(['user_id' => $this->staffA->id, 'distributor_id' => $this->distA->id, 'category' => 'fuel', 'amount' => 10, 'expense_date' => now()->toDateString()]);
        $this->actingAs($this->staffA, 'web')->post(route('erp.expenses.review', $e), ['decision' => 'approved'])->assertForbidden();
        $this->get(route('erp.attendance'))->assertForbidden();
        $this->get(route('erp.expenses'))->assertForbidden();
    }

    // ---------------- dashboard ----------------

    public function test_dashboard_reflects_real_records(): void
    {
        $t = $this->token($this->staffA);
        $this->api('GET', '/dashboard', [], $t)->assertOk()
            ->assertJsonPath('data.orders_today.count', 0)->assertJsonPath('data.attendance.status', 'not_checked_in')
            ->assertJsonPath('data.quick_actions.check_in.enabled', true)->assertJsonPath('data.shop_visits.tracked', false);

        $this->api('POST', '/attendance/check-in', ['latitude' => 19.07, 'longitude' => 72.87], $t)->assertCreated();
        $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 2]])->assertCreated();   // 224.00
        $this->placeOrder($t, [['product_id' => $this->p2->id, 'qty' => 1]])->assertCreated();   // 50.00
        // Another staff member's order must not count.
        $this->api('POST', '/orders', ['shop_id' => $this->shopB->id, 'items' => [['product_id' => $this->p1->id, 'qty' => 1]]], $this->token($this->staffB), $this->key())->assertCreated();

        $this->api('GET', '/dashboard', [], $t)->assertOk()
            ->assertJsonPath('data.staff.id', $this->staffA->id)->assertJsonPath('data.distributor.name', 'Alpha Pharma')
            ->assertJsonPath('data.orders_today.count', 2)->assertJsonPath('data.orders_today.value', '274.00')
            ->assertJsonPath('data.attendance.status', 'checked_in')
            ->assertJsonPath('data.quick_actions.check_in.enabled', false)->assertJsonPath('data.quick_actions.check_out.enabled', true)
            ->assertJsonPath('data.deliveries.confirmed', 2)->assertJsonPath('data.shop_visits.shops_served_today', 1)
            ->assertJsonCount(2, 'data.recent_orders');
    }

    // ---------------- isolation & admin visibility ----------------

    public function test_staff_cannot_reach_another_distributors_records(): void
    {
        $tB = $this->token($this->staffB);
        $orderB = $this->api('POST', '/orders', ['shop_id' => $this->shopB->id, 'items' => [['product_id' => $this->p1->id, 'qty' => 1]]], $tB, $this->key())->json('data.id');
        $this->api('POST', '/collections', ['shop_id' => $this->shopB->id, 'amount' => 10, 'method' => 'cash'], $tB, $this->key())->assertCreated();
        $this->api('POST', '/expenses', ['category' => 'fuel', 'amount' => 10, 'expense_date' => BusinessDay::today()], $tB, $this->key())->assertCreated();
        // Same-distributor colleague's order is also not visible to staff A unless assigned to them.
        $colleague = User::create(['name' => 'Colleague', 'email' => 'col@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $this->distA->id]);
        $colleagueOrder = $this->placeOrder($this->token($colleague), [['product_id' => $this->p1->id, 'qty' => 1]])->json('data.id');

        $t = $this->token($this->staffA);
        $this->api('GET', '/orders/'.$orderB, [], $t)->assertStatus(404)->assertJsonPath('message', 'Resource not found.');
        $this->api('GET', '/orders/'.$colleagueOrder, [], $t)->assertStatus(404);
        $this->api('GET', '/orders', [], $t)->assertJsonCount(0, 'data');
        $this->api('GET', '/parties/'.$this->shopB->id.'/invoices', [], $t)->assertStatus(404);
        $this->api('POST', '/orders', ['shop_id' => $this->shopB->id, 'items' => [['product_id' => $this->p1->id, 'qty' => 1]]], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('shop_id');
        $this->api('POST', '/collections', ['shop_id' => $this->shopB->id, 'amount' => 10, 'method' => 'cash'], $t, $this->key())
            ->assertStatus(422)->assertJsonValidationErrors('shop_id');
        $this->api('GET', '/collections', [], $t)->assertJsonCount(0, 'data');
        $this->api('GET', '/expenses', [], $t)->assertJsonCount(0, 'data');
        $this->api('GET', '/products/'.$this->p1->id, [], $t)->assertJsonPath('data.available_qty', 49); // own distributor's stock: 50 less the colleague's reservation of 1
        $this->assertSame(1, Order::where('distributor_id', $this->distB->id)->count());
    }

    public function test_admin_panel_shows_mobile_records(): void
    {
        $t = $this->token($this->staffA);
        $order = $this->placeOrder($t, [['product_id' => $this->p1->id, 'qty' => 1]])->json('data.number');
        $receipt = $this->api('POST', '/collections', ['shop_id' => $this->shopA->id, 'amount' => 75, 'method' => 'cash', 'remarks' => 'Cash from counter'], $t, $this->key())->json('data.receipt_number');
        $this->api('POST', '/expenses', ['category' => 'courier', 'amount' => 40, 'expense_date' => BusinessDay::today(), 'description' => 'Courier to depot'], $t, $this->key())->assertCreated();
        $this->api('POST', '/attendance/check-in', ['latitude' => 19.07, 'longitude' => 72.87], $t)->assertCreated();

        $this->asAdmin();
        $this->get(route('erp.orders'))->assertOk()->assertSee($order)->assertSee('Alpha Staff');
        $this->get(route('erp.payments'))->assertOk()->assertSee($receipt)->assertSee('Cash from counter')->assertSee('Verify');
        $this->get(route('erp.expenses'))->assertOk()->assertSee('Courier to depot');
        $this->get(route('erp.attendance'))->assertOk()->assertSee('Alpha Staff')->assertSee('19.07');
        $this->get(route('erp.dashboard'))->assertOk()->assertSee('Expenses to review');

        // Distributor B's owner does not see A's records.
        $ownerB = User::create(['name' => 'Owner B', 'email' => 'ownerb@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $this->distB->id]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($ownerB, 'web');
        $this->get(route('erp.payments'))->assertOk()->assertDontSee($receipt);
        $this->get(route('erp.expenses'))->assertOk()->assertDontSee('Courier to depot');
        $this->get(route('erp.attendance'))->assertOk()->assertDontSee('Alpha Staff');

        // Verification happens only in the Admin Panel.
        $this->asAdmin();
        app(SalesService::class)->verifyPayment(Payment::firstOrFail());
        $this->api('GET', '/collections', [], $t)->assertJsonPath('data.0.verified', true);
    }

    public function test_admin_can_configure_geofence(): void
    {
        $this->post(route('erp.distributors.geofence', $this->distA), ['latitude' => 18.94, 'longitude' => 72.83, 'geofence_radius_m' => 150])->assertSessionHasNoErrors();
        $this->assertTrue($this->distA->fresh()->hasGeofence());
        $this->api('GET', '/attendance/today', [], $this->token($this->staffA))->assertJsonPath('data.geofence.configured', true)->assertJsonPath('data.geofence.radius_m', 150);
        $this->asAdmin();
        $this->post(route('erp.distributors.geofence', $this->distA), ['latitude' => '', 'longitude' => '', 'geofence_radius_m' => ''])->assertSessionHasNoErrors();
        $this->assertFalse($this->distA->fresh()->hasGeofence());
    }
}
