<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferFormAndWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
    }

    public function test_every_distributor_is_a_destination_even_without_a_stock_location(): void
    {
        $this->actingAs($this->admin());
        Location::create(['name' => 'Main Warehouse', 'type' => 'warehouse']);
        $withLoc = Distributor::create(['business_name' => 'Alpha']);
        Location::create(['name' => 'Alpha', 'type' => 'distributor', 'distributor_id' => $withLoc->id]);
        $imported = Distributor::create(['business_name' => 'Imported Pharma']);
        $inactive = Distributor::create(['business_name' => 'Sleepy Meds', 'status' => 'inactive']);

        $this->get(route('erp.transfers'))->assertOk()
            ->assertSee('Alpha · Distributor', false)
            ->assertSee('Imported Pharma · Distributor', false)
            ->assertSee('Sleepy Meds · Distributor (inactive)', false);
        $this->assertTrue(Location::where('type', 'distributor')->where('distributor_id', $imported->id)->exists());
        $this->assertSame(1, Location::where('distributor_id', $inactive->id)->count());

        $this->get(route('erp.transfers'))->assertOk();
        $this->assertSame(3, Location::where('type', 'distributor')->count(), 'locations are created once');
    }

    public function test_form_shows_free_stock_per_location_and_blocks_sending_more_than_is_free(): void
    {
        $this->actingAs($this->admin());
        $wh = Location::create(['name' => 'Main Warehouse', 'type' => 'warehouse']);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $dl = Location::create(['name' => 'Alpha', 'type' => 'distributor', 'distributor_id' => $d->id]);
        $p = Product::create(['name' => 'Acne Cure Drop', 'sku' => 'ACN', 'selling_price' => 10]);
        app(StockService::class)->move($p->id, Location::system('external'), $wh, 100, 'opening', 'o1');

        $this->get(route('erp.transfers'))->assertOk()
            ->assertSee('data-transfer-stock', false)
            ->assertSee('"'.$wh->id.'":{"'.$p->id.'":100}', false);
        $this->getJson(route('erp.transfers.stock', ['location' => $wh->id]))->assertOk()
            ->assertJsonPath('location_id', $wh->id)->assertJsonPath('stock.'.$p->id, 100);

        $this->post(route('erp.transfers.store'), ['from_location_id' => $wh->id, 'to_location_id' => $dl->id, 'lines' => [$p->id => 150]])
            ->assertSessionHasErrors(['lines' => 'Not enough stock at Main Warehouse for Acne Cure Drop (sending 150, only 100 available). Lower these quantities, or receive more stock first.']);
        $this->assertSame(0, Transfer::count());

        $this->post(route('erp.transfers.store'), ['from_location_id' => $wh->id, 'to_location_id' => $dl->id, 'lines' => [$p->id => 100]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Transfer::count());
    }

    public function test_admin_deletes_an_unused_warehouse_and_archives_one_with_history(): void
    {
        $this->actingAs($this->admin());
        $unused = Location::create(['name' => 'Spare', 'type' => 'warehouse']);
        $used = Location::create(['name' => 'Old Depot', 'type' => 'warehouse']);
        $main = Location::create(['name' => 'Main', 'type' => 'warehouse']);
        $p = Product::create(['name' => 'Syrup', 'sku' => 'S1', 'selling_price' => 10]);
        $stock = app(StockService::class);
        $stock->move($p->id, Location::system('external'), $used, 10, 'opening', 'o1');

        $this->post(route('erp.warehouses.delete', $used))
            ->assertSessionHasErrors(['action' => '"Old Depot" still holds 10 unit(s). Transfer them to another location or write them off first, then delete the warehouse.']);

        $stock->move($p->id, $used, $main, 10, 'transfer_dispatch', 'mv1');
        $this->post(route('erp.warehouses.delete', $used))->assertSessionHasNoErrors();
        $this->assertNotNull($used->fresh()->archived_at, 'used warehouse is archived, not deleted');
        $this->assertSame(2, StockMovement::count(), 'ledger history is kept');

        $this->post(route('erp.warehouses.delete', $unused))->assertSessionHasNoErrors();
        $this->assertNull(Location::find($unused->id));

        $this->get(route('erp.stock'))->assertOk()->assertDontSee('Old Depot · ', false);
        $this->post(route('erp.stock.receive'), ['location_id' => $used->id, 'product_id' => $p->id, 'qty' => 1, 'type' => 'receipt', 'key' => 'k1'])
            ->assertSessionHasErrors('location_id');
        $this->post(route('erp.warehouses.store'), ['name' => 'Old Depot'])->assertSessionHasNoErrors();
    }

    public function test_warehouse_with_open_transfer_cannot_be_deleted_and_only_admins_may_delete(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $wh = Location::create(['name' => 'Main', 'type' => 'warehouse']);
        $other = Location::create(['name' => 'Second', 'type' => 'warehouse']);
        $p = Product::create(['name' => 'Syrup', 'sku' => 'S1', 'selling_price' => 10]);
        app(StockService::class)->move($p->id, Location::system('external'), $wh, 10, 'opening', 'o1');
        $t = Transfer::create(['number' => 'TR-1', 'from_location_id' => $wh->id, 'to_location_id' => $other->id, 'created_by' => $admin->id, 'status' => 'draft']);

        $this->post(route('erp.warehouses.delete', $other))
            ->assertSessionHasErrors(['action' => '"Second" has open transfers (TR-1). Finish them before deleting the warehouse.']);

        $manager = User::create(['name' => 'Inv', 'email' => 'inv@x.test', 'password' => 'secret-password', 'role' => 'inventory_manager']);
        $this->actingAs($manager)->post(route('erp.warehouses.delete', $other))->assertForbidden();
        $this->assertNotNull(Location::find($other->id));
    }
}
