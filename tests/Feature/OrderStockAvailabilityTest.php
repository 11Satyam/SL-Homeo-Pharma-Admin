<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_form_carries_free_stock_and_short_order_lists_every_shortage(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $loc = Location::create(['name' => 'Alpha', 'type' => 'distributor', 'distributor_id' => $d->id]);
        $shop = Shop::create(['name' => 'City Medical', 'distributor_id' => $d->id]);
        $p1 = Product::create(['name' => 'Paracetamol', 'sku' => 'P1', 'selling_price' => 10]);
        $p2 = Product::create(['name' => 'Cough Syrup', 'sku' => 'P2', 'selling_price' => 50]);
        $this->actingAs($admin);
        app(StockService::class)->move($p1->id, Location::system('external'), $loc, 10, 'opening', 'o1');
        app(SalesService::class)->createOrder($shop, [$p1->id => 4]); // 4 reserved, 6 free

        $this->get(route('erp.orders'))->assertOk()
            ->assertSee('data-order-stock', false)
            ->assertSee('"stock":{"'.$d->id.'":{"'.$p1->id.'":6}}', false)
            ->assertSee('data-dist="'.$d->id.'"', false);

        $this->post(route('erp.orders.store'), ['shop_id' => $shop->id, 'lines' => [$p1->id => 8, $p2->id => 2]])
            ->assertSessionHasErrors(['action' => 'Not enough stock for Paracetamol (ordered 8, only 6 in stock), Cough Syrup (ordered 2, none in stock). Lower these quantities or remove the item, or get more stock transferred to this distributor first.']);
        $this->assertSame(1, Order::count(), 'short order is not created');
        $this->assertSame(4, (int) StockBalance::where('location_id', $loc->id)->where('product_id', $p1->id)->value('reserved'), 'nothing extra stays reserved');
    }

    public function test_live_stock_endpoint_returns_current_free_stock_for_the_shops_distributor_only(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $other = Distributor::create(['business_name' => 'Beta']);
        $loc = Location::create(['name' => 'Alpha', 'type' => 'distributor', 'distributor_id' => $d->id]);
        $otherLoc = Location::create(['name' => 'Beta', 'type' => 'distributor', 'distributor_id' => $other->id]);
        $shop = Shop::create(['name' => 'City Medical', 'distributor_id' => $d->id]);
        $otherShop = Shop::create(['name' => 'Far Pharmacy', 'distributor_id' => $other->id]);
        $p = Product::create(['name' => 'Paracetamol', 'sku' => 'P1', 'selling_price' => 10]);
        $this->actingAs($admin);
        app(StockService::class)->move($p->id, Location::system('external'), $loc, 10, 'opening', 'o1');
        app(StockService::class)->move($p->id, Location::system('external'), $otherLoc, 3, 'opening', 'o2');

        $this->getJson(route('erp.orders.stock', ['shop' => $shop->id]))->assertOk()
            ->assertJson(['distributor_id' => $d->id, 'stock' => [$p->id => 10]]);

        // Another order reserves stock: the next poll sees the drop without reloading the page.
        app(SalesService::class)->createOrder($shop, [$p->id => 7]);
        $this->getJson(route('erp.orders.stock', ['shop' => $shop->id]))->assertJsonPath('stock.'.$p->id, 3);

        // A distributor user cannot read another distributor's stock.
        $distUser = User::create(['name' => 'Dist', 'email' => 'dist@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $d->id]);
        $this->actingAs($distUser)->getJson(route('erp.orders.stock', ['shop' => $otherShop->id]))->assertForbidden();
    }
}
