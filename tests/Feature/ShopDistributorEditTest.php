<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDistributorEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
    }

    private function distributor(string $name): array
    {
        $d = Distributor::create(['business_name' => $name]);
        $loc = Location::create(['name' => $name, 'type' => 'distributor', 'distributor_id' => $d->id]);
        $owner = User::create(['name' => $name.' Owner', 'email' => strtolower($name).'@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $d->id]);

        return [$d, $loc, $owner];
    }

    private function shopData(array $over = []): array
    {
        return $over + ['name' => 'New Name Medicals', 'owner' => 'O', 'phone' => '9800000000', 'area' => 'Andheri', 'address' => 'Road 1',
            'credit_limit' => 25000, 'payment_terms_days' => 21, 'status' => 'active'];
    }

    public function test_admin_edits_shop_and_moves_it_while_it_has_no_history(): void
    {
        [$dA] = $this->distributor('Alpha');
        [$dB] = $this->distributor('Beta');
        $shop = Shop::create(['name' => 'Old Name', 'distributor_id' => $dA->id]);

        $this->actingAs($this->admin)->post(route('erp.shops.update', $shop), $this->shopData(['distributor_id' => $dB->id, 'status' => 'inactive']))->assertSessionHasNoErrors();
        $shop->refresh();
        $this->assertSame(['New Name Medicals', 21, 'inactive', $dB->id], [$shop->name, (int) $shop->payment_terms_days, $shop->status, (int) $shop->distributor_id]);
        $this->get(route('erp.shops'))->assertOk()->assertSee('Edit shop · New Name Medicals')->assertSee('Edit distributor');
    }

    public function test_shop_with_orders_cannot_change_distributor(): void
    {
        [$dA, $locA] = $this->distributor('Alpha');
        [$dB] = $this->distributor('Beta');
        $shop = Shop::create(['name' => 'Busy Shop', 'distributor_id' => $dA->id]);
        $p = Product::create(['name' => 'P', 'sku' => 'P1', 'selling_price' => 10]);
        $this->actingAs($this->admin);
        app(StockService::class)->move($p->id, Location::system('external'), $locA, 10, 'opening', 'o1');
        app(SalesService::class)->createOrder($shop, [$p->id => 1]);

        $this->post(route('erp.shops.update', $shop), $this->shopData(['distributor_id' => $dB->id]))->assertSessionHasErrors('distributor_id');
        $this->assertSame($dA->id, (int) $shop->fresh()->distributor_id);
        // Other fields can still be edited.
        $this->post(route('erp.shops.update', $shop), $this->shopData(['distributor_id' => $dA->id]))->assertSessionHasNoErrors();
        $this->assertSame('New Name Medicals', $shop->fresh()->name);
    }

    public function test_distributor_can_edit_only_own_shops_and_cannot_move_them(): void
    {
        [$dA, , $ownerA] = $this->distributor('Alpha');
        [$dB] = $this->distributor('Beta');
        $mine = Shop::create(['name' => 'Mine', 'distributor_id' => $dA->id]);
        $theirs = Shop::create(['name' => 'Theirs', 'distributor_id' => $dB->id]);

        $this->actingAs($ownerA)->post(route('erp.shops.update', $mine), $this->shopData(['distributor_id' => $dB->id]))->assertSessionHasNoErrors();
        $this->assertSame($dA->id, (int) $mine->fresh()->distributor_id, 'distributor_id ignored for non-admins');
        $this->post(route('erp.shops.update', $theirs), $this->shopData())->assertForbidden();
        $this->post(route('erp.distributors.update', $dA), ['business_name' => 'Hacked'])->assertForbidden();
    }

    public function test_admin_edits_distributor_and_location_name_follows(): void
    {
        [$dA, $locA] = $this->distributor('Alpha');
        $this->distributor('Beta');

        $this->actingAs($this->admin)->post(route('erp.distributors.update', $dA), ['business_name' => 'Alpha Pharma Pvt Ltd', 'area' => 'Thane', 'credit_limit' => 500000])->assertSessionHasNoErrors();
        $this->assertSame(['Alpha Pharma Pvt Ltd', 'Thane'], [$dA->fresh()->business_name, $dA->fresh()->area]);
        $this->assertSame('Alpha Pharma Pvt Ltd', $locA->fresh()->name);

        $this->post(route('erp.distributors.update', $dA), ['business_name' => 'beta'])->assertSessionHasErrors('business_name');
        $this->get(route('erp.distributors'))->assertOk()->assertSee('Edit details');
    }
}
