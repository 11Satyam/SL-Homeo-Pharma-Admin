<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed product categories. Products keep the category name in products.category (so the API and
 * filters are unchanged); this table is the list the product form picks from. Existing names are carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->timestamps();
        });

        $now = now();
        $names = DB::table('products')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')
            ->map(fn ($n) => trim($n))->filter()->unique(fn ($n) => mb_strtolower($n))->values();
        foreach ($names as $n) {
            DB::table('product_categories')->insert(['name' => $n, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
