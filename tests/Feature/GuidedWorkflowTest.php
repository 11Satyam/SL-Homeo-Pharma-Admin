<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StockService;
use App\Support\Guide;
use App\Support\Help;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole business day through the web UI, as each role would do it:
 * receive 100 -> send 20 to a distributor -> sell 5 to a shop -> collect -> verify -> return 2.
 */
class GuidedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/erp', '/erp/help', '/erp/products', '/erp/stock', '/erp/ledger', '/erp/transfers', '/erp/shops',
        '/erp/orders', '/erp/invoices', '/erp/payments', '/erp/attendance', '/erp/expenses', '/erp/users', '/erp/distributors', '/erp/audit'];

    private function qty(Location $l, Product $p): int
    {
        return (int) StockBalance::where('location_id', $l->id)->where('product_id', $p->id)->value('qty');
    }

    private function as(User $u): self
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u);
    }

    public function test_complete_daily_workflow_with_guidance_and_mistake_prevention(): void
    {
        $admin = User::create(['name' => 'Priya Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->as($admin);
        $this->assertCount(10, Guide::checklist($admin));
        $this->assertSame(0, collect(Guide::checklist($admin))->where(1, true)->count(), 'fresh system: nothing set up');
        $this->get('/erp/help')->assertOk()->assertSee('Getting started as Admin')->assertSee('Worked example')->assertSee('Create a warehouse');

        // 1. Warehouse (duplicates refused)
        $this->post('/erp/warehouses', ['name' => 'Main Warehouse'])->assertSessionHasNoErrors()->assertSessionHas('next');
        $this->post('/erp/warehouses', ['name' => 'main warehouse'])->assertSessionHasErrors('name');
        $wh = Location::where('type', 'warehouse')->sole();

        // 2. Product (duplicate SKU explained in plain words)
        $product = ['name' => 'Paracetamol 500', 'sku' => 'PARA500', 'unit' => 'strip', 'cost_price' => 7, 'selling_price' => 10, 'tax_rate' => 0, 'reorder_level' => 20, 'active' => 1];
        $this->post('/erp/products', $product)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'added'));
        $this->post('/erp/products', $product)->assertSessionHasErrors(['sku' => 'Another product already uses this code (SKU). Each product needs its own code.']);
        $p = Product::sole();

        // 3. Receive 100; the same form submitted twice is not counted twice
        $receive = ['location_id' => $wh->id, 'product_id' => $p->id, 'qty' => 100, 'type' => 'opening', 'key' => 'form-1'];
        $this->post('/erp/stock/receive', $receive)->assertSessionHas('status', fn ($s) => str_contains($s, 'Available there now: 100'));
        $this->post('/erp/stock/receive', $receive)->assertSessionHas('status', fn ($s) => str_contains($s, 'not added twice'));
        $this->assertSame(100, $this->qty($wh, $p));
        $this->post('/erp/stock/adjust', ['location_id' => $wh->id, 'product_id' => $p->id, 'qty' => 500, 'reason' => 'x', 'key' => 'adj-1'])->assertSessionHasErrors('qty');

        // 4. Distributor (login + stock location created, next step points at a transfer to it)
        $this->post('/erp/distributors', ['business_name' => 'Sharma Distributors', 'contact_person' => 'Anil Sharma', 'email' => 'anil@x.test', 'area' => 'Andheri'])
            ->assertSessionHasNoErrors()->assertSessionHas('next', fn ($n) => str_contains($n[0][1], 'to='));
        $this->post('/erp/distributors', ['business_name' => 'sharma distributors', 'email' => 'other@x.test'])->assertSessionHasErrors('business_name');
        $dist = Distributor::sole();
        $dl = Location::where('distributor_id', $dist->id)->sole();
        $owner = User::where('email', 'anil@x.test')->sole();
        $this->assertSame('distributor', $owner->role);

        // 5. Transfer 20 with "approve now"; mistakes caught before anything is saved
        $this->post('/erp/transfers', ['from_location_id' => $wh->id, 'to_location_id' => $wh->id, 'lines' => [$p->id => 5]])->assertSessionHasErrors('to_location_id');
        $this->post('/erp/transfers', ['from_location_id' => $wh->id, 'to_location_id' => $dl->id, 'lines' => [$p->id => '']])->assertSessionHasErrors('lines');
        $this->post('/erp/transfers', ['from_location_id' => $wh->id, 'to_location_id' => $dl->id, 'lines' => [$p->id => 20], 'approve_now' => 1])->assertSessionHasNoErrors();
        $t = Transfer::sole();
        $this->assertSame('approved', $t->status);
        $this->assertSame(100, $this->qty($wh, $p), 'approval alone never moves stock');
        $this->assertContains('approved transfer(s) to dispatch', array_column(Guide::tasks($admin), 'title'));
        $this->post("/erp/transfers/{$t->id}/dispatch")->assertSessionHas('status', fn ($s) => str_contains($s, 'dispatched') && ! str_contains($s, 'dispatchd'));
        $this->assertSame(80, $this->qty($wh, $p));
        $this->assertSame(20, $this->qty(Location::system('transit'), $p));

        // 6. Distributor receives (an empty receipt is refused)
        $this->as($owner);
        $this->assertContains('stock delivery(ies) on the way to you', array_column(Guide::tasks($owner), 'title'));
        $line = $t->lines()->sole();
        $this->post("/erp/transfers/{$t->id}/receive", ['received' => [$line->id => 0], 'damaged' => [$line->id => 0]])->assertSessionHasErrors('received');
        $this->post("/erp/transfers/{$t->id}/receive", ['received' => [$line->id => 20], 'damaged' => [$line->id => 0]])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'available to sell'));
        $this->assertSame(20, $this->qty($dl, $p));

        // 7. Staff and shop (role is fixed to staff for a distributor; duplicate shop refused)
        $this->post('/erp/users', ['name' => 'Ravi Kumar', 'email' => 'ravi@x.test', 'role' => 'staff'])->assertSessionHasNoErrors();
        $this->post('/erp/users', ['name' => 'Boss', 'email' => 'boss@x.test', 'role' => 'admin'])->assertSessionHasErrors('role');
        $staff = User::where('email', 'ravi@x.test')->sole();
        $this->assertSame($dist->id, $staff->distributor_id);
        $this->post('/erp/shops', ['name' => 'City Medical', 'area' => 'Andheri West', 'phone' => '9820000001'])->assertSessionHasNoErrors();
        $this->post('/erp/shops', ['name' => 'CITY MEDICAL', 'area' => 'andheri west'])->assertSessionHasErrors('name');
        $this->post('/erp/shops', ['name' => 'City Medical', 'area' => 'Bandra', 'phone' => '9820000001'])->assertSessionHasErrors('name');
        $this->post('/erp/shops', ['name' => 'City Medical', 'area' => 'Bandra'])->assertSessionHasNoErrors(); // same name, another area: a different shop
        $shop = Shop::where('area', 'Andheri West')->sole();
        $this->assertSame(15, $shop->payment_terms_days, 'sensible default terms');

        // Admin forgetting the distributor gets a field error, not an error page.
        $this->as($admin)->post('/erp/users', ['name' => 'Lost', 'email' => 'lost@x.test', 'role' => 'staff'])->assertSessionHasErrors('distributor_id');

        // 8. Staff takes an order of 5 (double submit ignored, overselling refused)
        $this->as($staff);
        $order = ['shop_id' => $shop->id, 'lines' => [$p->id => 5], 'key' => 'order-form-1', 'remarks' => 'Deliver before noon'];
        $this->post('/erp/orders', $order)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'set aside'));
        $this->post('/erp/orders', $order)->assertSessionHas('status', fn ($s) => str_contains($s, 'not created twice'));
        $this->assertSame(1, Order::count());
        $this->post('/erp/orders', ['shop_id' => $shop->id, 'lines' => [$p->id => 999]])->assertSessionHasErrors(['action' => 'Insufficient available stock for Paracetamol 500.']);
        $this->post('/erp/orders', ['shop_id' => $shop->id, 'lines' => [$p->id => 0]])->assertSessionHasErrors('lines');
        $o = Order::sole();
        $this->assertSame('Deliver before noon', $o->remarks);
        $this->assertSame(20, $this->qty($dl, $p), 'ordering reserves, it does not deduct');
        $this->assertSame(15, app(StockService::class)->available($dl, $p->id));
        $this->assertContains('order(s) assigned to you to deliver', array_column(Guide::tasks($staff), 'title'));

        // 9. Pack, out for delivery, deliver (failure needs a reason)
        $this->post("/erp/orders/{$o->id}/packed")->assertSessionHasNoErrors();
        $this->post("/erp/orders/{$o->id}/out_for_delivery")->assertSessionHasNoErrors();
        $this->post("/erp/orders/{$o->id}/failed")->assertSessionHasErrors('reason');
        $this->post("/erp/orders/{$o->id}/deliver", ['delivered' => [$o->lines()->sole()->id => 5]])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'invoice') && str_contains($s, '₹50.00'))->assertSessionHas('next');
        $this->assertSame(15, $this->qty($dl, $p));
        $inv = Invoice::sole();
        $this->assertSame('50.00', $inv->outstanding());

        // 10. Collect 30 (UPI needs a reference; auto-allocated to the oldest invoice; double submit ignored)
        $this->post('/erp/payments', ['shop_id' => $shop->id, 'amount' => 30, 'method' => 'upi', 'auto' => 1])->assertSessionHasErrors('reference');
        $pay = ['shop_id' => $shop->id, 'amount' => 30, 'method' => 'cash', 'auto' => 1, 'key' => 'pay-form-1'];
        $this->post('/erp/payments', $pay)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'your manager'));
        $this->post('/erp/payments', $pay)->assertSessionHas('status', fn ($s) => str_contains($s, 'not recorded twice'));
        $this->assertSame(1, Payment::count());
        $this->assertSame('20.00', $inv->fresh()->outstanding());
        $payment = Payment::sole();
        $this->post("/erp/payments/{$payment->id}/verify")->assertForbidden();

        // 11. Distributor verifies, then records a return of 2 good strips
        $this->as($owner);
        $this->assertContains('payment(s) to verify', array_column(Guide::tasks($owner), 'title'));
        $this->post("/erp/payments/{$payment->id}/verify")->assertSessionHasNoErrors();
        $this->assertSame('verified', $payment->fresh()->status);
        $this->post("/erp/orders/{$o->id}/return", ['line_id' => $o->lines()->sole()->id, 'sellable' => 0, 'damaged' => 0, 'reason' => 'x'])->assertSessionHasErrors('sellable');
        $this->post("/erp/orders/{$o->id}/return", ['line_id' => $o->lines()->sole()->id, 'sellable' => 2, 'damaged' => 0, 'reason' => 'Excess'])->assertSessionHasNoErrors();
        $this->assertSame(17, $this->qty($dl, $p));
        $this->assertSame('0.00', $inv->fresh()->outstanding());

        // Distributor checklist reflects real progress; ledger still reconciles.
        $done = collect(Guide::checklist($owner))->where(1, true)->count();
        $this->assertSame(count(Guide::checklist($owner)), $done, 'every distributor setup step is now done');
        $this->assertSame([], app(StockService::class)->reconcile());

        // Every page works for every role, and help is tailored to the role.
        $roles = ['inventory_manager', 'accounts_manager', 'viewer'];
        $people = [$admin, $owner, $staff] + [];
        foreach ($roles as $role) {
            $people[] = User::create(['name' => ucfirst($role), 'email' => "{$role}@x.test", 'password' => 'secret-password', 'role' => $role]);
        }
        foreach ($people as $u) {
            $this->as($u);
            foreach (self::PAGES as $url) {
                $status = $this->get($url)->status();
                $this->assertContains($status, [200, 403], "{$u->role} {$url}");
            }
            $this->get('/erp')->assertOk();
            $this->get('/erp/help')->assertOk()->assertSee(\App\Support\Ui::label($u->role));
            foreach (Help::guide($u) as [, , $route]) {
                if ($route) {
                    $this->get(route($route))->assertOk(); // guides never send someone to a page they cannot open
                }
            }
        }
    }

    public function test_filters_used_by_dashboard_links(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->as($admin);
        foreach (['/erp/transfers?status=draft', '/erp/transfers?status=approved', '/erp/transfers?status=in_transit', '/erp/orders?status=open',
            '/erp/payments?status=collected', '/erp/invoices?status=overdue', '/erp/invoices?status=unpaid', '/erp/expenses?status=submitted',
            '/erp/orders?new=1', '/erp/stock?new=1', '/erp/shops?new=1'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (Guide::actions($admin) as [, $url]) {
            $this->get($url)->assertOk();
        }
    }

    public function test_demo_seeder_builds_a_consistent_company(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame([], app(StockService::class)->reconcile(), 'every demo balance matches the ledger');
        $this->assertSame(4, Distributor::count());
        $this->assertGreaterThanOrEqual(30, Shop::count());
        $this->assertGreaterThanOrEqual(18, Product::count());
        $this->assertGreaterThan(50, Invoice::count());
        $orderStatuses = Order::distinct()->pluck('status')->all();
        foreach (['confirmed', 'delivered', 'failed', 'cancelled', 'partially_delivered'] as $s) {
            $this->assertContains($s, $orderStatuses, "demo has a {$s} order");
        }
        $this->assertEqualsCanonicalizing(['draft', 'approved', 'in_transit', 'received'], Transfer::distinct()->pluck('status')->all());
        $this->assertTrue(Payment::where('status', 'verified')->exists() && Payment::where('status', 'collected')->exists());
        $this->assertSame(['approved', 'rejected', 'submitted'], Expense::distinct()->orderBy('status')->pluck('status')->all());
        $this->assertTrue(Invoice::overdue()->exists(), 'some invoices are overdue');
        $this->assertNotEmpty(Guide::lowStock(User::where('email', 'admin@slpharma.test')->sole()), 'warehouse low-stock alerts');
        $this->assertTrue(\App\Models\Attendance::where('check_in_within_geofence', false)->exists());

        // Re-running is harmless.
        $before = Order::count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($before, Order::count());

        foreach ((new DemoSeeder)->logins() as [, $email]) {
            $u = User::where('email', $email)->sole();
            $this->as($u);
            if (! $u->hasRole('viewer')) { // read-only auditors have no tasks by design
                $this->assertNotEmpty(Guide::tasks($u), "{$email} has something to do today");
            }
            foreach (self::PAGES as $url) {
                $this->assertContains($this->get($url)->status(), [200, 403], "{$email} {$url}");
            }
        }
        // Demo password works on the real login form.
        $this->app['auth']->forgetGuards();
        $this->post('/logout');
        $this->post('/login', ['email' => 'ravi.kumar@slpharma.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs(User::where('email', 'ravi.kumar@slpharma.test')->sole());
    }
}
