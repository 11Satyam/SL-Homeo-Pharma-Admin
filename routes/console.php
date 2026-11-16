<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Secure Super Admin provisioning: no default/hardcoded credentials.
Artisan::command('erp:create-admin {email} {name=Administrator}', function (string $email, string $name) {
    $password = $this->secret('Password (min 12 chars)');
    if (strlen((string) $password) < 12 || $password !== $this->secret('Confirm password')) {
        return $this->error('Passwords must match and be at least 12 characters.');
    }
    $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => 'admin', 'status' => 'active']);
    Location::firstOrCreate(['type' => 'warehouse', 'distributor_id' => null], ['name' => 'Main Warehouse']);
    $this->info("Admin {$user->email} ready. Log in at /login.");
})->purpose('Create or reset a Super Admin account');

// Fill check-in/out place names for attendance rows that only have coordinates (1 lookup per second: map service limit).
Artisan::command('attendance:addresses {--limit=500}', function (\App\Services\Geocoder $geo) {
    if (! $geo->enabled()) {
        return $this->error('Geocoding is off (FIELDAPP_GEOCODE=false).');
    }
    $done = 0;
    \App\Models\Attendance::where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('check_in_lat')->whereNull('check_in_address'))
        ->orWhere(fn ($q) => $q->whereNotNull('check_out_lat')->whereNull('check_out_address')))
        ->orderByDesc('id')->limit((int) $this->option('limit'))->get()
        ->each(function ($a) use ($geo, &$done) {
            if ($geo->fill($a)) {
                $done++;
                sleep(1);
            }
        });
    $this->info("Looked up {$done} attendance record(s).");
})->purpose('Fill attendance place names from coordinates');
