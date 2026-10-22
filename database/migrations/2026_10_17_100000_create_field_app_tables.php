<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional office geofence per distributor for attendance.
        Schema::table('distributors', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->unsignedInteger('geofence_radius_m')->nullable();
        });

        // Idempotency keys are per creating user so a retried POST returns the original record.
        Schema::table('orders', function (Blueprint $t) {
            $t->string('remarks', 500)->nullable();
            $t->string('idempotency_key', 64)->nullable();
            $t->unique(['created_by', 'idempotency_key']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->string('receipt_number')->nullable()->unique();
            $t->string('remarks', 500)->nullable();
            $t->timestamp('collected_at')->nullable();
            $t->string('idempotency_key', 64)->nullable();
            $t->unique(['collected_by', 'idempotency_key']);
        });

        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('distributor_id')->constrained();
            $t->date('date');
            $t->string('status')->default('checked_in'); // checked_in | checked_out
            foreach (['check_in', 'check_out'] as $p) {
                $t->timestamp($p.'_at')->nullable();          // server time, authoritative
                $t->timestamp($p.'_client_at')->nullable();   // as reported by device
                $t->decimal($p.'_lat', 10, 7)->nullable();
                $t->decimal($p.'_lng', 10, 7)->nullable();
                $t->decimal($p.'_accuracy_m', 8, 2)->nullable();
                $t->unsignedInteger($p.'_distance_m')->nullable();
                $t->boolean($p.'_within_geofence')->nullable(); // null = no geofence configured
            }
            $t->string('remarks', 500)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'date']);
            $t->index(['distributor_id', 'date']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('distributor_id')->constrained();
            $t->string('category');
            $t->decimal('amount', 14, 2);
            $t->date('expense_date');
            $t->string('description', 500)->nullable();
            $t->string('receipt_path')->nullable();
            $t->string('status')->default('submitted'); // submitted | approved | rejected
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->string('idempotency_key', 64)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'idempotency_key']);
            $t->index(['distributor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('attendances');
        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique(['collected_by', 'idempotency_key']);
            $t->dropUnique(['receipt_number']);
            $t->dropColumn(['receipt_number', 'remarks', 'collected_at', 'idempotency_key']);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['created_by', 'idempotency_key']);
            $t->dropColumn(['remarks', 'idempotency_key']);
        });
        Schema::table('distributors', function (Blueprint $t) {
            $t->dropColumn(['latitude', 'longitude', 'geofence_radius_m']);
        });
    }
};
