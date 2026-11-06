<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\StockTrace;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class DistributorStockTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Distributor $dist;
    private Location $loc;
    private Location $wh;
    private Product $p;
    private string $transferNumber;
    private string $orderNumber;

    /**
     * 40 sent from the warehouse: 35 received, 2 damaged in transit, 3 still on the way.
     * 10 ordered, 8 delivered; 1 returned good and 1 damaged.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->actingAs($this->admin);
        $this->wh = Location::create(['name' => 'Main Warehouse', 'type' => 'warehouse']);
        $this->dist = Distributor::create(['business_name' => 'Alpha Pharma', 'area' => 'Andheri']);
        $this->loc = Location::create(['name' => 'Alpha Pharma', 'type' => 'distributor', 'distributor_id' => $this->dist->id]);
        $this->p = Product::create(['name' => 'Fever Drop', 'sku' => 'FVR', 'selling_price' => 10]);
        app(StockService::class)->move($this->p->id, Location::system('external'), $this->wh, 100, 'opening', 'o1');

        $transfers = app(TransferService::class);
        $t = $transfers->create($this->wh, $this->loc, [$this->p->id => 40]);
        $transfers->approve($t);
        $transfers->dispatch($t);
        $line = $t->lines()->first();
        $transfers->receive($t, [$line->id => 35], [$line->id => 2]);
        $this->transferNumber = $t->number;

        $sales = app(SalesService::class);
        $shop = Shop::create(['name' => 'City Chemist', 'distributor_id' => $this->dist->id]);
        $order = $sales->createOrder($shop, [$this->p->id => 10]);
        $ol = $order->lines()->first();
        $sales->deliver($order, [$ol->id => 8]);
        $sales->processReturn($order->fresh(), $ol->id, 1, 1, 'Near expiry');
        $this->orderNumber = $order->number;
    }

    private function metrics(): array
    {
        return app(StockTrace::class)->metrics($this->dist->fresh());
    }

    private function op(array $data = [])
    {
        return $this->post(route('erp.distributors.stock', $this->dist), $data + ['kind' => 'missing', 'product_id' => $this->p->id, 'qty' => 2, 'reason' => 'Count short', 'key' => 'k1']);
    }

    public function test_summary_figures_come_from_the_ledger(): void
    {
        $m = $this->metrics();
        $t = $m['totals'];

        $this->assertTrue($m['reconciled']);
        $this->assertSame(40, $t['allocated']);
        $this->assertSame(3, $t['transit_in']);
        $this->assertSame(8, $t['sold']);
        $this->assertSame(2, $t['returned']);
        $this->assertSame(3, $t['damaged'], '2 in transit + 1 returned damaged');
        $this->assertSame(28, $t['on_hand'], '35 received - 8 sold + 1 good return');
        $this->assertSame(28, $t['available']);
    }

    public function test_reserved_stock_lowers_available_but_not_on_hand(): void
    {
        $shop = Shop::where('name', 'City Chemist')->first();
        app(SalesService::class)->createOrder($shop, [$this->p->id => 5]);
        $t = $this->metrics()['totals'];

        $this->assertSame(28, $t['on_hand']);
        $this->assertSame(5, $t['reserved']);
        $this->assertSame(23, $t['available']);
    }

    public function test_a_reversal_cancels_the_figure_it_undoes(): void
    {
        $this->op()->assertRedirect();
        $this->assertSame(2, $this->metrics()['totals']['missing']);

        app(StockService::class)->reverse(StockMovement::where('type', 'stock_missing')->first(), 'Found it');
        $m = $this->metrics();
        $this->assertSame(0, $m['totals']['missing']);
        $this->assertSame(28, $m['totals']['on_hand']);
        $this->assertTrue($m['reconciled']);
    }

    public function test_date_range_limits_flows_but_not_live_stock(): void
    {
        $t = app(StockTrace::class)->metrics($this->dist, now()->addDay()->toDateString(), now()->addDays(2)->toDateString())['totals'];

        $this->assertSame(0, $t['sold']);
        $this->assertSame(0, $t['allocated']);
        $this->assertSame(28, $t['on_hand']);
        $this->assertSame(3, $t['transit_in']);
    }

    public function test_page_tabs_render_with_details_and_figures(): void
    {
        $url = route('erp.distributors.show', $this->dist);
        $this->get($url)->assertOk()->assertSee('Alpha Pharma')->assertSee('Andheri')->assertSee('Total allocated')->assertSee('Fever Drop');
        $this->get($url.'?tab=transfers')->assertOk()->assertSee($this->transferNumber)->assertSee('Partially received');
        $this->get($url.'?tab=returns')->assertOk()->assertSee($this->orderNumber)->assertSee('Receive return');
        $this->get($url.'?tab=activity')->assertOk()->assertSee('by Admin')->assertSee('Transfer Approved');
    }

    public function test_history_includes_legs_that_never_touch_the_distributor_location(): void
    {
        $this->get(route('erp.distributors.show', [$this->dist, 'tab' => 'movements']))->assertOk()
            ->assertSee('Transfer dispatch')->assertSee('Main Warehouse')->assertSee('Transit damage')
            ->assertSee('Return (damaged)')->assertSee('City Chemist')->assertSee($this->orderNumber);
    }

    public function test_history_filters_and_remaining_stock(): void
    {
        $this->op(['qty' => 3, 'kind' => 'damaged']);
        $url = route('erp.distributors.show', $this->dist);

        $this->get($url.'?tab=movements&kind=out')->assertOk()->assertSee('Sale (delivery)')->assertDontSee('Write-off');
        $this->get($url.'?tab=movements&q=ADJ-')->assertOk()->assertSee('Write-off')->assertDontSee('Sale (delivery)');
        $this->get($url.'?tab=movements&from='.now()->addDay()->toDateString())->assertOk()->assertSee('No movements match these filters');
        $this->get($url.'?tab=movements&product_id='.$this->p->id)->assertOk()->assertSee('Main Warehouse · 60');

        $rows = app(StockTrace::class)->decorate(StockMovement::with('product', 'from', 'to')->orderBy('id')->get(), $this->loc)->keyBy(fn ($x) => $x->m->type);
        $this->assertSame(35, $rows['transfer_receive']->remaining);
        $this->assertSame(27, $rows['sale']->remaining);
        $this->assertSame(28, $rows['return']->remaining);
        $this->assertSame(25, $rows['damage_adjustment']->remaining);
        $this->assertSame(-3, $rows['damage_adjustment']->signed);
        $this->assertNull($rows['transfer_dispatch']->remaining);
    }

    public function test_stock_operations_post_ledger_entries_with_an_audit_trail(): void
    {
        $this->op(['kind' => 'increase', 'qty' => 4, 'key' => 'a'])->assertSessionHas('status');
        $this->op(['kind' => 'decrease', 'qty' => 1, 'key' => 'b']);
        $this->op(['kind' => 'missing', 'qty' => 2, 'key' => 'c']);

        $m = $this->metrics();
        $this->assertSame(3, $m['totals']['adjusted']);
        $this->assertSame(2, $m['totals']['missing']);
        $this->assertSame(29, $m['totals']['on_hand']);
        $this->assertTrue($m['reconciled']);
        $this->assertSame([], app(StockService::class)->reconcile());
        $entry = StockMovement::where('type', 'stock_missing')->first();
        $this->assertSame('Count short', $entry->reason);
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertStringStartsWith('ADJ-', $entry->reference);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'StockMovement', 'entity_id' => $entry->id, 'action' => 'stock.stock_missing', 'user_id' => $this->admin->id]);
    }

    public function test_stock_operations_need_a_reason_free_stock_and_post_only_once(): void
    {
        $this->op(['reason' => ''])->assertSessionHasErrors('reason');
        $this->op(['qty' => 29])->assertSessionHasErrors('qty');

        app(SalesService::class)->createOrder(Shop::first(), [$this->p->id => 28]);
        $this->op(['qty' => 1])->assertSessionHasErrors('qty');
        $this->op(['kind' => 'increase', 'qty' => 1, 'key' => 'same']);
        $this->op(['kind' => 'increase', 'qty' => 1, 'key' => 'same'])->assertSessionHas('status', 'This entry was already saved, so it was not posted twice.');

        $this->assertSame(1, StockMovement::where('type', 'stock_adjustment')->count());
        $this->assertSame(29, (int) StockBalance::where('location_id', $this->loc->id)->value('qty'));
    }

    public function test_movement_detail_traces_the_document(): void
    {
        $sale = StockMovement::where('type', 'sale')->first();
        $this->get(route('erp.ledger.show', $sale))->assertOk()
            ->assertSee($this->orderNumber)->assertSee('City Chemist')->assertSee('INV-')->assertSee('Return (damaged)')->assertSee('Left at Alpha Pharma');

        $dispatch = StockMovement::where('type', 'transfer_dispatch')->first();
        $this->get(route('erp.ledger.show', $dispatch))->assertOk()->assertSee($this->transferNumber)->assertSee('Transfer receipt')->assertSee('Transit damage');
    }

    public function test_excel_export_is_a_valid_workbook(): void
    {
        foreach (['products', 'movements', 'activity'] as $report) {
            $res = $this->get(route('erp.distributors.export', [$this->dist, 'report' => $report, 'format' => 'xlsx']));
            $res->assertOk();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($res->baseResponse->getFile()->getPathname()));
            $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertNotFalse($xml);
            $this->assertGreaterThan(1, count($xml->sheetData->row));
            $zip->close();
        }
    }

    public function test_pdf_view_and_distributor_report(): void
    {
        $this->get(route('erp.distributors.export', [$this->dist, 'report' => 'movements', 'format' => 'pdf']))->assertOk()
            ->assertSee('Save as PDF')->assertSee('Stock movement history')->assertSee($this->orderNumber);
        $this->get(route('erp.distributors.report'))->assertOk()->assertSee('Alpha Pharma');
        $this->get(route('erp.distributors.report', ['format' => 'xlsx']))->assertOk();
        $this->get(route('erp.distributors.report', ['format' => 'pdf']))->assertOk()->assertSee('Save as PDF');
    }

    public function test_only_admins_reach_these_pages(): void
    {
        $this->post(route('logout'));
        foreach (['distributor', 'inventory_manager', 'viewer'] as $role) {
            $u = User::create(['name' => $role, 'email' => $role.'@x.test', 'password' => 'secret-password', 'role' => $role, 'distributor_id' => $role === 'distributor' ? $this->dist->id : null]);
            $this->actingAs($u);
            $this->get(route('erp.distributors.show', $this->dist))->assertForbidden();
            $this->get(route('erp.distributors.report'))->assertForbidden();
            $this->get(route('erp.ledger.show', StockMovement::first()))->assertForbidden();
            $this->op()->assertForbidden();
        }
        $this->assertSame(0, StockMovement::where('type', 'stock_missing')->count());
    }
}
