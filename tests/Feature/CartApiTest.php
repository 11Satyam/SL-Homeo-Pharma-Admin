<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Shop $shop;

    private Location $loc;

    private Product $p1;

    private Product $p2;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->actingAs($admin);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $this->loc = Location::create(['name' => 'Alpha', 'type' => 'distributor', 'distributor_id' => $d->id]);
        $this->staff = User::create(['name' => 'Ravi', 'email' => 'ravi@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);
        $this->shop = Shop::create(['name' => 'Gupta Medicals', 'distributor_id' => $d->id]);
        $this->p1 = Product::create(['name' => 'Gas Care Drop', 'sku' => 'GAS30', 'unit' => 'bottle', 'selling_price' => 175, 'tax_rate' => 5]);
        $this->p2 = Product::create(['name' => 'Fever Drop', 'sku' => 'FEV30', 'unit' => 'bottle', 'selling_price' => 160, 'tax_rate' => 5]);
        $stock = app(StockService::class);
        $stock->move($this->p1->id, Location::system('external'), $this->loc, 20, 'opening', 'o1');
        $stock->move($this->p2->id, Location::system('external'), $this->loc, 5, 'opening', 'o2');
        $this->token = $this->staff->createToken('t', ['field-app'], now()->addDay())->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        config(['auth.defaults.guard' => 'web']);

        return $this->json($method, '/api/app'.$uri, $data, $headers + ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->token]);
    }

    private function add(Product $p, int $qty): TestResponse
    {
        return $this->api('POST', '/cart/items', ['shop_id' => $this->shop->id, 'product_id' => $p->id, 'qty' => $qty]);
    }

    public function test_adding_the_same_product_merges_quantity_and_totals_are_server_priced(): void
    {
        $this->add($this->p1, 2)->assertOk()->assertJsonPath('data.line_count', 1);
        $r = $this->add($this->p1, 3)->assertOk();
        $r->assertJsonPath('data.line_count', 1)->assertJsonPath('data.items.0.qty', 5)
            ->assertJsonPath('data.items.0.subtotal', '875.00')->assertJsonPath('data.items.0.tax', '43.75')
            ->assertJsonPath('data.items.0.total', '918.75')->assertJsonPath('data.total', '918.75')->assertJsonPath('data.can_checkout', true);
        $this->add($this->p2, 1)->assertJsonPath('data.line_count', 2)->assertJsonPath('data.unit_count', 6)->assertJsonPath('data.total', '1086.75');
        $this->assertSame(2, Cart::first()->items()->count());
    }

    public function test_add_validates_stock_status_quantity_and_shop(): void
    {
        $this->add($this->p2, 6)->assertStatus(422)->assertJsonValidationErrors('qty');
        $this->add($this->p2, 4)->assertOk();
        $this->add($this->p2, 2)->assertStatus(422)->assertJsonPath('errors.qty.0', 'You already have 4 in the cart. Only 5 bottle of Fever Drop available.');
        $this->add($this->p1, 0)->assertStatus(422)->assertJsonValidationErrors('qty');

        $inactive = Product::create(['name' => 'Old', 'sku' => 'OLD', 'selling_price' => 10, 'active' => false]);
        $this->add($inactive, 1)->assertStatus(422)->assertJsonValidationErrors('product_id');
        $this->api('POST', '/cart/items', ['shop_id' => $this->shop->id, 'product_id' => 99999, 'qty' => 1])->assertStatus(422)->assertJsonValidationErrors('product_id');

        $otherShop = Shop::create(['name' => 'Elsewhere', 'distributor_id' => Distributor::create(['business_name' => 'Beta'])->id]);
        $this->api('POST', '/cart/items', ['shop_id' => $otherShop->id, 'product_id' => $this->p1->id, 'qty' => 1])->assertStatus(422)->assertJsonValidationErrors('shop_id');
        $this->api('GET', '/cart?shop_id='.$otherShop->id)->assertStatus(422);
    }

    public function test_update_remove_and_clear(): void
    {
        $this->add($this->p1, 2);
        $this->add($this->p2, 1);
        $this->api('PATCH', '/cart/items/'.$this->p1->id, ['shop_id' => $this->shop->id, 'qty' => 7])->assertOk()->assertJsonPath('data.items.0.qty', 7);
        $this->api('PATCH', '/cart/items/'.$this->p1->id, ['shop_id' => $this->shop->id, 'qty' => 21])->assertStatus(422)->assertJsonValidationErrors('qty');
        $this->api('PATCH', '/cart/items/'.$this->p2->id, ['shop_id' => $this->shop->id, 'qty' => 0])->assertOk()->assertJsonPath('data.line_count', 1);
        $this->api('PATCH', '/cart/items/'.$this->p2->id, ['shop_id' => $this->shop->id, 'qty' => 1])->assertStatus(422)->assertJsonValidationErrors('product_id');

        $this->api('DELETE', '/cart/items/'.$this->p1->id.'?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.line_count', 0);
        $this->api('DELETE', '/cart/items/'.$this->p1->id.'?shop_id='.$this->shop->id)->assertStatus(422);

        $this->add($this->p1, 1);
        $this->api('DELETE', '/cart?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.line_count', 0);
        $this->assertSame(0, Cart::count());
    }

    public function test_cart_reflects_live_price_and_flags_stock_problems(): void
    {
        $this->add($this->p2, 5);
        $this->p2->update(['selling_price' => 200]);
        $this->api('GET', '/cart?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.items.0.price', '200.00')->assertJsonPath('data.subtotal', '1000.00');

        // Someone else's order takes 3 units: the cart now asks for more than is free.
        StockBalance::where('location_id', $this->loc->id)->where('product_id', $this->p2->id)->increment('reserved', 3);
        $this->api('GET', '/cart?shop_id='.$this->shop->id)->assertJsonPath('data.can_checkout', false)
            ->assertJsonPath('data.items.0.issue', 'insufficient_stock')->assertJsonPath('data.items.0.available_qty', 2)
            ->assertJsonPath('data.issues.0.code', 'insufficient_stock');
        $this->api('GET', '/carts')->assertOk()->assertJsonPath('data.0.shop.id', $this->shop->id);
    }

    public function test_checkout_creates_order_reserves_stock_empties_cart_and_is_idempotent(): void
    {
        $this->add($this->p1, 4);
        $this->add($this->p2, 2);
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id])->assertStatus(422)->assertJsonValidationErrors('idempotency_key');

        $r = $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id, 'remarks' => 'Urgent'], $key)->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.remarks', 'Urgent')->assertJsonPath('data.total', '1071.00');
        $order = Order::findOrFail($r->json('data.id'));
        $this->assertSame([$this->staff->id, $this->staff->id], [(int) $order->staff_id, (int) $order->created_by]);
        $this->assertSame(4, (int) StockBalance::where('location_id', $this->loc->id)->where('product_id', $this->p1->id)->value('reserved'));
        $this->assertSame(0, Cart::count(), 'cart emptied');

        $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id], $key)->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $order->id);
        $this->assertSame(1, Order::count());
        $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('cart');
    }

    public function test_checkout_fails_cleanly_when_stock_or_product_changed(): void
    {
        $this->add($this->p2, 5);
        StockBalance::where('location_id', $this->loc->id)->where('product_id', $this->p2->id)->increment('reserved', 3);
        $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonPath('code', 'insufficient_stock');
        $this->assertSame(0, Order::count());
        $this->assertSame(5, Cart::first()->items()->value('qty'), 'cart kept so the user can fix it');

        $this->api('PATCH', '/cart/items/'.$this->p2->id, ['shop_id' => $this->shop->id, 'qty' => 2])->assertOk();
        $this->p2->update(['active' => false]);
        $this->api('POST', '/cart/checkout', ['shop_id' => $this->shop->id], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('cart');
    }

    public function test_carts_are_private_to_each_staff_member(): void
    {
        $this->add($this->p1, 3);
        $colleague = User::create(['name' => 'Col', 'email' => 'col@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $this->shop->distributor_id]);
        $this->token = $colleague->createToken('t', ['field-app'], now()->addDay())->plainTextToken;
        $this->api('GET', '/cart?shop_id='.$this->shop->id)->assertOk()->assertJsonPath('data.line_count', 0);
        $this->api('GET', '/carts')->assertJsonCount(0, 'data');
    }

    public function test_cart_requires_login(): void
    {
        $this->token = 'invalid';
        $this->api('GET', '/cart?shop_id='.$this->shop->id)->assertStatus(401);
    }
}
