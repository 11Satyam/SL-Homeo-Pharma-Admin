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
