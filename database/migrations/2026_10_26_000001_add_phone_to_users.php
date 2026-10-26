<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mobile number used as a login identifier for the field app (normalised to 10 digits).
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 15)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['phone']);
            $t->dropColumn('phone');
        });
    }
};
