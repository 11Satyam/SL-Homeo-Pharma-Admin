<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A deleted warehouse with ledger history is archived instead, so its stock movements keep their location. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $t) {
            $t->timestamp('archived_at')->nullable()->after('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $t) {
            $t->dropColumn('archived_at');
        });
    }
};
