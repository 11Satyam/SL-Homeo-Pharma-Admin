<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed staff expense categories (previously fixed in config/fieldapp.php). Expenses keep the category
 * code in expenses.category, so a code never changes after creation: renaming only changes the label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 100);
            $t->boolean('active')->default(true);
            $t->boolean('receipt_required')->default(false);
            $t->decimal('max_amount', 12, 2)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        $now = now();
        foreach (['travel' => 'Travel', 'fuel' => 'Fuel', 'food' => 'Food', 'lodging' => 'Lodging', 'phone' => 'Phone',
            'courier' => 'Courier', 'stationery' => 'Stationery', 'other' => 'Other'] as $code => $name) {
            DB::table('expense_categories')->insert(['code' => $code, 'name' => $name, 'sort' => $code === 'other' ? 999 : 0,
                'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
