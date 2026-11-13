<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Distributor;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AddShopApiTest extends TestCase
{
    use RefreshDatabase;

    private Distributor $dist;

    private User $staff;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dist = Distributor::create(['business_name' => 'Alpha']);
        $this->staff = User::create(['name' => 'Ravi', 'email' => 'ravi@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $this->dist->id]);
        $this->token = $this->staff->createToken('t', ['field-app'], now()->addDay())->plainTextToken;
    }

    private function add(array $data, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/app/parties', $data, ['Authorization' => 'Bearer '.($token ?? $this->token)]);
    }

    public function test_staff_adds_a_shop_for_their_own_distributor_with_default_terms(): void
    {
        $this->add(['name' => 'Gupta Medicals', 'owner' => 'R. Gupta', 'phone' => '9876543210', 'area' => 'Andheri West', 'address' => 'Shop 4, SV Road',
            'distributor_id' => 999, 'credit_limit' => 100000, 'payment_terms_days' => 90, 'status' => 'inactive'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gupta Medicals')->assertJsonPath('data.owner', 'R. Gupta')->assertJsonPath('data.area', 'Andheri West')
            ->assertJsonPath('data.credit_limit', '0.00')->assertJsonPath('data.payment_terms_days', 15);

        $shop = Shop::sole();
        $this->assertSame($this->dist->id, $shop->distributor_id, 'distributor comes from the token, never the request');
        $this->assertSame('active', $shop->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shop.created', 'entity' => 'Shop', 'entity_id' => $shop->id, 'user_id' => $this->staff->id]);
        $this->assertSame('field_app', AuditLog::where('action', 'shop.created')->sole()->data['source']);

        $this->getJson('/api/app/parties?search=Gupta', ['Authorization' => 'Bearer '.$this->token])->assertJsonPath('data.0.id', $shop->id);
    }

    public function test_name_is_required_and_lengths_are_limited(): void
    {
        $this->add(['owner' => 'X'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->add(['name' => 'A', 'phone' => str_repeat('9', 31), 'area' => str_repeat('a', 101)])->assertStatus(422)->assertJsonValidationErrors(['phone', 'area']);
        $this->assertSame(0, Shop::count());
    }

    public function test_a_duplicate_or_retry_returns_the_existing_shop_with_409(): void
    {
        $id = $this->add(['name' => 'Gupta Medicals', 'area' => 'Andheri', 'phone' => '9876543210'])->assertCreated()->json('data.id');

        $this->add(['name' => ' gupta medicals ', 'area' => 'andheri'])->assertStatus(409)
            ->assertJsonPath('code', 'duplicate_shop')->assertJsonPath('data.id', $id);
        $this->add(['name' => 'Gupta Medicals', 'area' => 'Bandra', 'phone' => '9876543210'])->assertStatus(409)->assertJsonPath('data.id', $id);
        $this->add(['name' => 'Gupta Medicals', 'area' => 'Bandra'])->assertCreated();
        $this->assertSame(2, Shop::count());
    }

    public function test_the_same_shop_may_exist_under_another_distributor(): void
    {
        $other = Distributor::create(['business_name' => 'Beta']);
        Shop::create(['name' => 'Gupta Medicals', 'area' => 'Andheri', 'distributor_id' => $other->id]);

        $this->add(['name' => 'Gupta Medicals', 'area' => 'Andheri'])->assertCreated();
    }

    public function test_an_inactive_duplicate_is_explained(): void
    {
        Shop::create(['name' => 'Old Chemist', 'distributor_id' => $this->dist->id, 'status' => 'inactive']);

        $this->add(['name' => 'Old Chemist'])->assertStatus(409)->assertJsonPath('message', '"Old Chemist" already exists. It is inactive: ask your distributor to activate it.');
    }

    public function test_requires_a_field_app_token(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/app/parties', ['name' => 'X'])->assertUnauthorized();
        $other = $this->staff->createToken('other', ['reports'])->plainTextToken;
        $this->add(['name' => 'X'], $other)->assertUnauthorized()->assertJsonPath('code', 'invalid_token');
        $this->assertSame(0, Shop::count());
    }
}
