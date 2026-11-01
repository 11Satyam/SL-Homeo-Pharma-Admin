<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One open cart per field staff member per shop. Only product + quantity is stored:
        // prices, tax and availability are always recomputed from the product master and stock ledger.
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('distributor_id')->constrained();
            $t->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'shop_id']);
        });

        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('qty');
            $t->timestamps();
            $t->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
