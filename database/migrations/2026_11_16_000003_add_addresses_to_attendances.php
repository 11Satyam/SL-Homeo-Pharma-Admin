<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Human-readable place for each check-in/out, looked up once from the device coordinates (reverse geocoding). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $t) {
            $t->string('check_in_address', 255)->nullable()->after('check_in_within_geofence');
            $t->string('check_out_address', 255)->nullable()->after('check_out_within_geofence');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $t) {
            $t->dropColumn(['check_in_address', 'check_out_address']);
        });
    }
};
