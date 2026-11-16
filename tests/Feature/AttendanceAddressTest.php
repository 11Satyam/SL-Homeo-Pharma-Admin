<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Distributor;
use App\Models\User;
use App\Services\Geocoder;
use App\Support\BusinessDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceAddressTest extends TestCase
{
    use RefreshDatabase;

    private const LUCKNOW = ['address' => ['road' => 'Mahatma Gandhi Marg', 'suburb' => 'Hazratganj', 'city' => 'Lucknow',
        'state' => 'Uttar Pradesh', 'postcode' => '226001', 'country' => 'India']];

    private function row(array $extra = []): Attendance
    {
        $d = Distributor::create(['business_name' => 'Alpha']);
        $u = User::create(['name' => 'Ravi', 'email' => 'r'.uniqid().'@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);

        return Attendance::create(['user_id' => $u->id, 'distributor_id' => $d->id, 'date' => BusinessDay::today(), 'status' => 'checked_in',
            'check_in_at' => now(), 'check_in_lat' => 26.85716, 'check_in_lng' => 80.96009, 'check_in_accuracy_m' => 20] + $extra);
    }

    public function test_label_is_short_and_readable(): void
    {
        $this->assertSame('Mahatma Gandhi Marg, Hazratganj, Lucknow, Uttar Pradesh 226001', Geocoder::label(self::LUCKNOW));
        $this->assertSame('A, B, C', Geocoder::label(['display_name' => 'A, B, C, India']));
        $this->assertNull(Geocoder::label([]));
    }

    public function test_attendance_page_shows_the_place_and_saves_it_once(): void
    {
        config(['fieldapp.attendance.geocode' => true]);
        Http::fake(['*' => Http::response(self::LUCKNOW)]);
        $a = $this->row();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);

        $this->actingAs($admin)->get('/erp/attendance')->assertOk()->assertSee('Hazratganj, Lucknow')->assertDontSee('>26.85716, 80.96009<', false);
        $this->assertSame('Mahatma Gandhi Marg, Hazratganj, Lucknow, Uttar Pradesh 226001', $a->fresh()->check_in_address);

        $this->get('/erp/attendance')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_coordinates_stay_when_the_map_service_fails(): void
    {
        config(['fieldapp.attendance.geocode' => true]);
        Http::fake(['*' => Http::response('', 500)]);
        $a = $this->row();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => 'admin']);

        $this->actingAs($admin)->get('/erp/attendance')->assertOk()->assertSee('26.85716, 80.96009');
        $this->assertNull($a->fresh()->check_in_address);
    }

    public function test_check_in_looks_up_the_place_after_responding(): void
    {
        config(['fieldapp.attendance.geocode' => true]);
        Http::fake(['*' => Http::response(self::LUCKNOW)]);
        $d = Distributor::create(['business_name' => 'Alpha']);
        $u = User::create(['name' => 'Ravi', 'email' => 'r@x.test', 'password' => 'secret-password', 'role' => 'staff', 'distributor_id' => $d->id]);
        $t = $u->createToken('t', ['field-app'], now()->addDay())->plainTextToken;

        $this->withoutDefer();
        $this->postJson('/api/app/attendance/check-in', ['latitude' => 26.85716, 'longitude' => 80.96009, 'accuracy_m' => 20],
            ['Authorization' => 'Bearer '.$t])->assertCreated();
        $this->assertSame('Mahatma Gandhi Marg, Hazratganj, Lucknow, Uttar Pradesh 226001', Attendance::sole()->check_in_address);
    }
}
