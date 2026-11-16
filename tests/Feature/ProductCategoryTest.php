<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);
    }

    private function product(?string $cat, string $sku = 'P1'): Product
    {
        return Product::create(['name' => 'Prod '.$sku, 'sku' => $sku, 'category' => $cat, 'unit' => 'strip', 'selling_price' => 10, 'cost_price' => 5, 'tax_rate' => 12]);
    }

    public function test_admin_adds_a_category_and_duplicates_are_refused_ignoring_case(): void
    {
        $this->actingAs($this->admin)->post('/erp/products/categories', ['name' => '  Syrup  '])->assertSessionHasNoErrors();
        $this->assertSame(['Syrup'], ProductCategory::pluck('name')->all());

        $this->post('/erp/products/categories', ['name' => 'syrup'])->assertSessionHasErrors('name');
        $this->post('/erp/products/categories', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(1, ProductCategory::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created']);

        $this->get('/erp/products')->assertOk()->assertSee('Product categories')->assertSee('<option >Syrup</option>', false);
    }

    public function test_rename_moves_its_products_and_delete_needs_it_unused(): void
    {
        $c = ProductCategory::create(['name' => 'Syrup']);
        $p = $this->product('Syrup');
        $this->actingAs($this->admin)->post("/erp/products/categories/{$c->id}", ['name' => 'Syrups'])->assertSessionHasNoErrors();
        $this->assertSame('Syrups', $p->fresh()->category);

        $this->post("/erp/products/categories/{$c->id}/delete")->assertSessionHasErrors('name');
        $this->assertModelExists($c);

        $p->update(['category' => null]);
        $this->post("/erp/products/categories/{$c->id}/delete")->assertSessionHasNoErrors();
        $this->assertModelMissing($c);
    }

    public function test_product_form_only_accepts_a_listed_category(): void
    {
        ProductCategory::create(['name' => 'Tablet']);
        $data = ['name' => 'Paracetamol', 'sku' => 'PCM', 'unit' => 'strip', 'cost_price' => 5, 'selling_price' => 10, 'tax_rate' => 12, 'reorder_level' => 0, 'active' => 1];

        $this->actingAs($this->admin)->post('/erp/products', $data + ['category' => 'Made Up'])->assertSessionHasErrors('category');
        $this->post('/erp/products', $data + ['category' => 'Tablet'])->assertSessionHasNoErrors();
        $this->post('/erp/products', ['sku' => 'PCM2'] + $data + ['category' => ''])->assertSessionHasNoErrors();
        $this->assertSame(['Tablet', null], Product::orderBy('id')->pluck('category')->all());
    }

    public function test_staff_cannot_manage_categories(): void
    {
        $staff = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'secret-password', 'role' => 'staff']);
        $this->actingAs($staff)->post('/erp/products/categories', ['name' => 'X'])->assertForbidden();
        $this->assertSame(0, ProductCategory::count());
    }
}
