<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $this->staff = User::create(['name' => 'Ravi', 'email' => 'r@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);
        $this->token = $this->staff->createToken('t', ['field-app'], now()->addDay())->plainTextToken;
    }

    private function claim(array $data): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/api/app/expenses', $data + ['amount' => 100, 'expense_date' => BusinessDay::today()],
            ['Authorization' => 'Bearer '.$this->token, 'Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json']);
    }

    public function test_the_old_fixed_list_is_carried_over(): void
    {
        $this->assertSame(['courier', 'food', 'fuel', 'lodging', 'other', 'phone', 'stationery', 'travel'], ExpenseCategory::orderBy('code')->pluck('code')->all());
    }

    public function test_admin_adds_a_category_and_the_app_can_use_it(): void
    {
        $this->actingAs($this->admin)->post('/erp/expenses/categories', ['name' => 'Doctor Samples', 'max_amount' => 500])->assertSessionHasNoErrors();
        $c = ExpenseCategory::where('name', 'Doctor Samples')->sole();
        $this->assertSame('doctor_samples', $c->code);
        $this->post('/erp/expenses/categories', ['name' => 'doctor samples'])->assertSessionHasErrors('name');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/app/session', ['Authorization' => 'Bearer '.$this->token])->assertOk()
            ->assertJsonFragment(['code' => 'doctor_samples', 'name' => 'Doctor Samples', 'max_amount' => '500.00']);

        $this->claim(['category' => 'doctor_samples', 'amount' => 600])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->claim(['category' => 'doctor_samples', 'amount' => 400])->assertCreated();
        $this->assertDatabaseHas('expenses', ['category' => 'doctor_samples']);
    }

    public function test_the_app_lists_only_categories_that_are_on(): void
    {
        ExpenseCategory::where('code', 'fuel')->update(['receipt_required' => true, 'max_amount' => 2000]);
        ExpenseCategory::where('code', 'phone')->update(['active' => false]);
        $res = $this->getJson('/api/app/expense-categories', ['Authorization' => 'Bearer '.$this->token])->assertOk()
            ->assertJsonCount(7, 'data')->assertJsonPath('data.6.code', 'other')->assertJsonPath('data.6.description_required', true)
            ->assertJsonPath('meta.max_amount', '100000.00')->assertJsonPath('meta.max_age_days', 30)
            ->assertJsonFragment(['code' => 'fuel', 'name' => 'Fuel', 'receipt_required' => true, 'description_required' => false, 'max_amount' => '2000.00']);
        $this->assertNotContains('phone', $res->json('data.*.code'));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/app/expense-categories')->assertOk()->assertJsonCount(7, 'data');
    }

    public function test_receipt_rule_and_turning_off_hide_it_from_the_app(): void
    {
        Storage::fake('local');
        $fuel = ExpenseCategory::where('code', 'fuel')->sole();
        $this->actingAs($this->admin)->post("/erp/expenses/categories/{$fuel->id}", ['name' => 'Fuel', 'receipt_required' => 1, 'active' => 1])->assertSessionHasNoErrors();
        $this->claim(['category' => 'fuel'])->assertStatus(422)->assertJsonValidationErrors('receipt');
        $this->claim(['category' => 'fuel', 'receipt' => UploadedFile::fake()->image('bill.jpg')])->assertCreated();

        $this->actingAs($this->admin)->post("/erp/expenses/categories/{$fuel->id}", ['name' => 'Petrol', 'active' => 0])->assertSessionHasNoErrors();
        $this->claim(['category' => 'fuel'])->assertStatus(422)->assertJsonValidationErrors('category');
        $this->assertSame('fuel', Expense::sole()->category, 'past claims keep the code');

        $this->actingAs($this->admin)->get('/erp/expenses')->assertOk()->assertSee('Petrol');
    }

    public function test_delete_only_when_unused_and_other_is_protected(): void
    {
        $food = ExpenseCategory::where('code', 'food')->sole();
        $this->claim(['category' => 'food'])->assertCreated();
        $this->actingAs($this->admin)->post("/erp/expenses/categories/{$food->id}/delete")->assertSessionHasErrors('name');
        $this->assertModelExists($food);

        $phone = ExpenseCategory::where('code', 'phone')->sole();
        $this->post("/erp/expenses/categories/{$phone->id}/delete")->assertSessionHasNoErrors();
        $this->assertModelMissing($phone);

        $other = ExpenseCategory::where('code', 'other')->sole();
        $this->post("/erp/expenses/categories/{$other->id}/delete")->assertSessionHasErrors('name');
        $this->post("/erp/expenses/categories/{$other->id}", ['name' => 'Other', 'active' => 0])->assertSessionHasErrors('name');
        $this->assertTrue($other->fresh()->active);
    }

    public function test_only_admin_manages_expense_categories(): void
    {
        $dist = User::create(['name' => 'D', 'email' => 'd@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $this->staff->distributor_id]);
        $this->actingAs($dist)->post('/erp/expenses/categories', ['name' => 'X'])->assertForbidden();
        $this->get('/erp/expenses')->assertOk()->assertDontSee('Expense categories</button>', false);
        $this->assertSame(0, ExpenseCategory::where('name', 'X')->count());
    }
}
