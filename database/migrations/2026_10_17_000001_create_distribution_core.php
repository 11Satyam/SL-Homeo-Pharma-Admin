<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $t) {
            $t->id();
            $t->string('business_name');
            $t->string('contact_person')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('area')->nullable();
            $t->text('address')->nullable();
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('viewer')->index();
            $t->string('status')->default('active');
            $t->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku')->unique();
            $t->string('category')->nullable();
            $t->string('unit')->default('pcs');
            $t->decimal('cost_price', 14, 2)->default(0);
            $t->decimal('selling_price', 14, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->integer('reorder_level')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // warehouse | distributor | transit | customer | external | damaged
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type')->index();
            $t->foreignId('distributor_id')->nullable()->constrained();
            $t->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->integer('qty')->default(0);
            $t->integer('reserved')->default(0);
            $t->timestamps();
            $t->unique(['location_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->string('idempotency_key')->unique();
            $t->foreignId('product_id')->constrained();
            $t->integer('qty');
            $t->foreignId('from_location_id')->constrained('locations');
            $t->foreignId('to_location_id')->constrained('locations');
            $t->string('type')->index();
            $t->string('reference')->nullable()->index();
            $t->string('reason')->nullable();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('reverses_id')->nullable()->constrained('stock_movements');
            $t->timestamps();
        });

        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('from_location_id')->constrained('locations');
            $t->foreignId('to_location_id')->constrained('locations');
            $t->string('status')->default('draft');
            $t->string('remarks')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });

        Schema::create('transfer_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->integer('qty');
            $t->integer('received_qty')->default(0);
            $t->integer('damaged_qty')->default(0);
        });

        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('owner')->nullable();
            $t->string('phone')->nullable();
            $t->string('area')->nullable();
            $t->text('address')->nullable();
            $t->foreignId('distributor_id')->constrained();
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->integer('payment_terms_days')->default(15);
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('shop_id')->constrained();
            $t->foreignId('distributor_id')->constrained();
            $t->foreignId('staff_id')->nullable()->constrained('users');
            $t->string('status')->default('confirmed');
            $t->decimal('total', 14, 2)->default(0);
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('delivered_at')->nullable();
            $t->string('failure_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->integer('qty');
            $t->integer('delivered_qty')->default(0);
            $t->integer('returned_qty')->default(0);
            $t->decimal('price', 14, 2);
            $t->decimal('tax_rate', 5, 2)->default(0);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('order_id')->unique()->constrained();
            $t->foreignId('shop_id')->constrained();
            $t->foreignId('distributor_id')->constrained();
            $t->decimal('total', 14, 2);
            $t->date('issue_date');
            $t->date('due_date');
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->constrained();
            $t->foreignId('distributor_id')->constrained();
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('reference')->nullable();
            // collected (by staff, unverified) -> verified (deposit reconciled)
            $t->string('status')->default('collected');
            $t->foreignId('collected_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained();
            $t->decimal('amount', 14, 2);
        });

        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained();
            $t->decimal('amount', 14, 2);
            $t->string('reason')->nullable();
            $t->string('stock_reference')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('entity')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('data')->nullable();
            $t->string('ip')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'credit_notes', 'payment_allocations', 'payments', 'invoices', 'order_lines', 'orders',
            'shops', 'transfer_lines', 'transfers', 'stock_movements', 'stock_balances', 'locations', 'products'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('distributor_id');
            $t->dropColumn(['role', 'status']);
        });
        Schema::dropIfExists('distributors');
    }
};
