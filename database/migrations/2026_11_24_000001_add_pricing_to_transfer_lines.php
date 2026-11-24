<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer lines carry a price snapshot (rate and GST from the product master at creation time) and a per-line
 * discount percentage, so a transfer invoice keeps showing what was agreed even if the product price changes later.
 * Existing lines are backfilled from the current product master with no discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_lines', function (Blueprint $t) {
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('discount_pct', 5, 2)->default(0);
        });

        DB::table('transfer_lines')->orderBy('id')->chunkById(500, function ($lines) {
            $products = DB::table('products')->whereIn('id', $lines->pluck('product_id'))->get(['id', 'selling_price', 'tax_rate'])->keyBy('id');
            foreach ($lines as $l) {
                if ($p = $products->get($l->product_id)) {
                    DB::table('transfer_lines')->where('id', $l->id)->update(['unit_price' => $p->selling_price, 'tax_rate' => $p->tax_rate]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfer_lines', function (Blueprint $t) {
            $t->dropColumn(['unit_price', 'tax_rate', 'discount_pct']);
        });
    }
};
