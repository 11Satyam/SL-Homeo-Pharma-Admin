<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Location $wh;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->actingAs($this->admin);
        $this->wh = Location::create(['name' => 'Main WH', 'type' => 'warehouse']);
        $this->p = Product::create(['name' => 'Para 500', 'sku' => 'P500', 'selling_price' => 10, 'tax_rate' => 0]);
    }

    private function distributor(string $name): array
    {
        $d = Distributor::create(['business_name' => $name]);
        $loc = Location::create(['name' => $name, 'type' => 'distributor', 'distributor_id' => $d->id]);
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $d->id]);

        return [$d, $loc, $user];
    }

    private function qty(Location $l): int
    {
        return (int) StockBalance::where('location_id', $l->id)->where('product_id', $this->p->id)->value('qty');
    }

    private function open(Location $to, int $qty, string $key): void
    {
        app(StockService::class)->move($this->p->id, Location::system('external'), $to, $qty, 'opening', $key);
    }

    public function test_mandatory_ledger_example_100_80_20_15_17(): void
    {
        $tr = app(TransferService::class);
        $sales = app(SalesService::class);
        [$d, $dl] = $this->distributor('DistA');

        $this->open($this->wh, 100, 'open-1');
        $this->assertSame(100, $this->qty($this->wh));

        $t = $tr->create($this->wh, $dl, [$this->p->id => 20]);
        $this->assertSame(100, $this->qty($this->wh), 'draft must not move stock');
        $tr->approve($t);
        $this->assertSame(100, $this->qty($this->wh), 'approval must not move stock');
        $tr->dispatch($t);
        $this->assertSame(80, $this->qty($this->wh));
        $this->assertSame(20, $this->qty(Location::system('transit')));
        $this->assertSame(0, $this->qty($dl));

        $tr->receive($t, [$t->lines[0]->id => 20]);
        $this->assertSame(0, $this->qty(Location::system('transit')));
        $this->assertSame(20, $this->qty($dl));
        $this->assertSame('received', $t->fresh()->status);

        $shop = Shop::create(['name' => 'S1', 'distributor_id' => $d->id]);
        $o = $sales->createOrder($shop, [$this->p->id => 5]);
        $this->assertSame(20, $this->qty($dl), 'reservation only, no deduction');
        $inv = $sales->deliver($o, [$o->lines[0]->id => 5]);
        $this->assertSame(15, $this->qty($dl));
        $this->assertEquals('50.00', $inv->total);

        $sales->processReturn($o->fresh(), $o->lines[0]->id, 2, 0, 'Excess');
        $this->assertSame(17, $this->qty($dl));
        $this->assertSame('30.00', $inv->fresh()->outstanding());

        $this->assertSame([], app(StockService::class)->reconcile());
    }

    public function test_independent_distributor_balances(): void
    {
        $tr = app(TransferService::class);
        $this->open($this->wh, 100, 'open-m');
        $locs = [];
        foreach (['M1', 'M2', 'M3', 'M4', 'M5'] as $n) {
            [, $locs[]] = $this->distributor($n);
        }
        foreach ($locs as $l) {
            $t = $tr->create($this->wh, $l, [$this->p->id => 20]);
            $tr->approve($t);
            $tr->dispatch($t);
            $tr->receive($t, [$t->lines[0]->id => 20]);
        }
        $this->assertSame(0, $this->qty($this->wh));
        foreach ($locs as $l) {
            $this->assertSame(20, $this->qty($l));
        }
    }

    public function test_partial_receipt_keeps_remainder_in_transit_and_damage_tracked(): void
    {
        $tr = app(TransferService::class);
        [, $dl] = $this->distributor('DistB');
        $this->open($this->wh, 50, 'open-2');
        $t = $tr->create($this->wh, $dl, [$this->p->id => 20]);
        $tr->approve($t);
        $tr->dispatch($t);
        $tr->receive($t, [$t->lines[0]->id => 12], [$t->lines[0]->id => 3]);
        $this->assertSame('partially_received', $t->fresh()->status);
        $this->assertSame(12, $this->qty($dl));
        $this->assertSame(5, $this->qty(Location::system('transit')));
        $this->assertSame(3, $this->qty(Location::system('damaged')));

        $this->expectException(RuntimeException::class);
        $tr->receive($t, [$t->lines[0]->id => 6]); // over-receipt
    }

    public function test_duplicate_dispatch_and_idempotent_posting(): void
    {
        $this->open($this->wh, 10, 'same-key');
        $this->open($this->wh, 10, 'same-key');
        $this->assertSame(10, $this->qty($this->wh));

        $tr = app(TransferService::class);
        [, $dl] = $this->distributor('DistC');
        $t = $tr->create($this->wh, $dl, [$this->p->id => 4]);
        $tr->approve($t);
        $tr->dispatch($t);
        $this->expectException(RuntimeException::class);
        $tr->dispatch($t->fresh());
    }

    public function test_internal_location_cannot_go_negative(): void
    {
        [, $dl] = $this->distributor('DistD');
        $this->expectException(RuntimeException::class);
        app(StockService::class)->move($this->p->id, $this->wh, $dl, 1, 'manual', 'neg-1');
    }

    public function test_cannot_oversell_and_cancel_releases_reservation(): void
    {
        $sales = app(SalesService::class);
        [$d, $dl] = $this->distributor('DistE');
        $this->open($dl, 5, 'o-e');
        $shop = Shop::create(['name' => 'S', 'distributor_id' => $d->id]);
        $o = $sales->createOrder($shop, [$this->p->id => 4]);
        try {
            $sales->createOrder($shop, [$this->p->id => 2]);
            $this->fail('Oversold reserved stock');
        } catch (RuntimeException) {
        }
        $sales->cancel($o, 'cancelled');
        $this->assertSame(5, app(StockService::class)->available($dl, $this->p->id));

        $this->expectException(RuntimeException::class);
        $sales->deliver($o->fresh(), [$o->lines[0]->id => 4]); // cancelled order cannot be fulfilled
    }

    public function test_duplicate_fulfillment_is_rejected(): void
    {
        $sales = app(SalesService::class);
        [$d, $dl] = $this->distributor('DistG');
        $this->open($dl, 10, 'o-g');
        $o = $sales->createOrder(Shop::create(['name' => 'S', 'distributor_id' => $d->id]), [$this->p->id => 3]);
        $sales->deliver($o, [$o->lines[0]->id => 3]);
        try {
            $sales->deliver($o->fresh(), [$o->lines[0]->id => 3]);
            $this->fail('Delivered twice');
        } catch (RuntimeException) {
        }
        $this->assertSame(7, $this->qty($dl));
    }

    public function test_payments_allocation_outstanding_and_verification(): void
    {
        $sales = app(SalesService::class);
        [$d, $dl] = $this->distributor('DistF');
        $this->open($dl, 100, 'o-f');
        $shop = Shop::create(['name' => 'S', 'distributor_id' => $d->id]);
        $o1 = $sales->createOrder($shop, [$this->p->id => 10]);
        $i1 = $sales->deliver($o1, [$o1->lines[0]->id => 10]); // 100.00
        $o2 = $sales->createOrder($shop, [$this->p->id => 5]);
        $i2 = $sales->deliver($o2, [$o2->lines[0]->id => 3]);  // partial: 30.00
        $this->assertSame('partially_delivered', $o2->fresh()->status);
        $this->assertSame(87, $this->qty($dl));
        $this->assertSame(87, app(StockService::class)->available($dl, $this->p->id), 'undelivered reservation released');

        // One payment across two invoices.
        $p = $sales->recordPayment($shop, '120.00', 'upi', 'UTR1', [$i1->id => 100, $i2->id => 20]);
        $this->assertSame('0.00', $i1->fresh()->outstanding());
        $this->assertSame('10.00', $i2->fresh()->outstanding());
        $this->assertSame('collected', $p->status, 'staff collection is not a verified deposit');
        $sales->verifyPayment($p);
        $this->assertSame('verified', $p->fresh()->status);

        // Second payment on the same invoice.
        $sales->recordPayment($shop, '10.00', 'cash', null, [$i2->id => 10]);
        $this->assertSame('0.00', $i2->fresh()->outstanding());

        $this->expectException(RuntimeException::class);
        $sales->recordPayment($shop, '5.00', 'cash', null, [$i2->id => 5]); // exceeds outstanding
    }

    public function test_reversal_restores_balance(): void
    {
        $stock = app(StockService::class);
        $m = $stock->move($this->p->id, Location::system('external'), $this->wh, 30, 'opening', 'o-r');
        $stock->reverse($m, 'Wrong qty');
        $this->assertSame(0, $this->qty($this->wh));
        $this->assertSame([], $stock->reconcile());
    }

    // ---------- Auth & permissions ----------

    public function test_login_redirect_failure_and_inactive_account(): void
    {
        auth()->logout();
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'secret-password'])->assertRedirect(route('erp.dashboard'));
        $this->assertAuthenticated();

        auth()->user()->update(['status' => 'suspended']);
        $this->get('/erp')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'secret-password'])->assertSessionHasErrors('email');
    }

    public function test_login_is_rate_limited(): void
    {
        auth()->logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@x.test', 'password' => 'bad']);
        }
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        auth()->logout();
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'a@x.test'])->assertSessionHas('status');
        $token = Password::createToken($this->admin);
        $this->post('/reset-password', ['token' => $token, 'email' => 'a@x.test', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'brand-new-pass'])->assertRedirect(route('erp.dashboard'));
    }

    public function test_guest_is_redirected_and_logout_works(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/erp/orders')->assertRedirect(route('login'));
    }

    public function test_cross_distributor_isolation(): void
    {
        [$dA] = $this->distributor('IsoA');
        [, , $userB] = $this->distributor('IsoB');
        $shopA = Shop::create(['name' => 'ShopOfA', 'distributor_id' => $dA->id]);

        $this->actingAs($userB);
        $this->get('/erp/shops/'.$shopA->id)->assertForbidden();
        $this->get('/erp/shops')->assertOk()->assertDontSee('ShopOfA');
        $this->post('/erp/orders', ['shop_id' => $shopA->id, 'lines' => [$this->p->id => 1]])->assertForbidden();
        $this->get('/erp/distributors')->assertForbidden();
        $this->post('/erp/products', ['name' => 'x'])->assertForbidden();
    }

    public function test_viewer_and_staff_restrictions(): void
    {
        $viewer = User::create(['name' => 'V', 'email' => 'v@x.test', 'password' => 'secret-password', 'role' => 'viewer']);
        $this->actingAs($viewer);
        $this->get('/erp/audit')->assertOk();
        $this->get('/erp/ledger')->assertOk();
        $this->post('/erp/transfers', [])->assertForbidden();
        $this->post('/erp/payments', [])->assertForbidden();

        [$d] = $this->distributor('StaffDist');
        $staff = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);
        $this->actingAs($staff);
        $this->post('/erp/stock/receive', [])->assertForbidden();
        $this->get('/erp/users')->assertForbidden();
        $this->get('/erp/orders')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $this->distributor('Render');
        foreach (['/erp', '/erp/products', '/erp/stock', '/erp/ledger', '/erp/transfers', '/erp/distributors', '/erp/users',
            '/erp/shops', '/erp/orders', '/erp/invoices', '/erp/payments', '/erp/audit'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
