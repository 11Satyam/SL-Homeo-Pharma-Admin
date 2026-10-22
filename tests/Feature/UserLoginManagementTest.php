<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserLoginManagementTest extends TestCase
{
    use RefreshDatabase;

    private function distributor(string $name): array
    {
        $d = Distributor::create(['business_name' => $name]);
        Location::create(['name' => $name, 'type' => 'distributor', 'distributor_id' => $d->id]);
        $owner = User::create(['name' => $name.' Owner', 'email' => strtolower($name).'@x.test', 'password' => 'secret-password', 'role' => 'distributor', 'distributor_id' => $d->id]);

        return [$d, $owner];
    }

    public function test_admin_creates_staff_with_password_who_can_use_the_app_immediately(): void
    {
        Notification::fake();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        [$d] = $this->distributor('Alpha');

        $this->actingAs($admin)->post(route('erp.users.store'), [
            'name' => 'Ravi Kumar', 'email' => 'ravi@x.test', 'role' => 'staff', 'distributor_id' => $d->id,
            'login' => 'password', 'password' => 'field-pass-1', 'password_confirmation' => 'field-pass-1',
        ])->assertSessionHasNoErrors();

        $u = User::where('email', 'ravi@x.test')->firstOrFail();
        $this->assertTrue(Hash::check('field-pass-1', $u->password), 'password is stored hashed');
        Notification::assertNothingSent();

        auth()->logout();
        $this->postJson('/api/app/auth/login', ['email' => 'ravi@x.test', 'password' => 'field-pass-1'])->assertOk()->assertJsonPath('data.user.role', 'staff');
    }

    public function test_invite_mode_still_emails_activation_and_password_is_validated(): void
    {
        Notification::fake();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $this->actingAs($admin)->post(route('erp.users.store'), ['name' => 'V', 'email' => 'v@x.test', 'role' => 'viewer', 'login' => 'invite'])->assertSessionHasNoErrors();
        Notification::assertSentTo(User::where('email', 'v@x.test')->first(), \Illuminate\Auth\Notifications\ResetPassword::class);

        $this->post(route('erp.users.store'), ['name' => 'W', 'email' => 'w@x.test', 'role' => 'viewer', 'login' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post(route('erp.users.store'), ['name' => 'W', 'email' => 'w@x.test', 'role' => 'viewer', 'login' => 'password', 'password' => 'long-enough-1', 'password_confirmation' => 'different-1'])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'w@x.test']);
    }

    public function test_changing_password_revokes_app_tokens_and_is_scoped(): void
    {
        [$dA, $ownerA] = $this->distributor('Alpha');
        [, $ownerB] = $this->distributor('Beta');
        $staff = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'old-password-1', 'role' => 'staff', 'distributor_id' => $dA->id]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'secret-password', 'role' => 'admin']);
        $staff->createToken('phone', ['field-app']);

        $this->actingAs($ownerA)->post(route('erp.users.password', $staff), ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-1', $staff->fresh()->password));
        $this->assertSame(0, $staff->tokens()->count(), 'mobile sessions are signed out');

        $this->post(route('erp.users.password', $staff), ['password' => 'x', 'password_confirmation' => 'x'])->assertSessionHasErrors('password');
        $this->actingAs($ownerB)->post(route('erp.users.password', $staff), ['password' => 'hijack-pass-1', 'password_confirmation' => 'hijack-pass-1'])->assertForbidden();
        $this->actingAs($ownerA)->post(route('erp.users.password', $admin), ['password' => 'hijack-pass-1', 'password_confirmation' => 'hijack-pass-1'])->assertForbidden();
        $this->assertTrue(Hash::check('new-password-1', $staff->fresh()->password));

        $this->actingAs($ownerA)->get(route('erp.users'))->assertOk()->assertSee('Change password')->assertSee('Mobile app');
    }
}
