<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferLine extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2', 'discount_pct' => 'decimal:2'];

    public function product() { return $this->belongsTo(Product::class); }

    /**
     * The one pricing formula for transfers (mirrored by the New transfer form in admin.js):
     * gross = rate × qty; discount = gross × disc% (on the pre-tax value); GST on the discounted value.
     * Each step is rounded to 2dp so the invoice adds up exactly.
     *
     * @return array{gross: float, discount: float, taxable: float, tax: float, total: float}
     */
    public static function amounts($price, int $qty, $discountPct = 0, $taxRate = 0): array
    {
        $gross = round((float) $price * $qty, 2);
        $discount = round($gross * min(100, max(0, (float) $discountPct)) / 100, 2);
        $taxable = round($gross - $discount, 2);
        $tax = round($taxable * (float) $taxRate / 100, 2);

        return ['gross' => $gross, 'discount' => $discount, 'taxable' => $taxable, 'tax' => $tax, 'total' => round($taxable + $tax, 2)];
    }

    public function totals(): array
    {
        return self::amounts($this->unit_price, (int) $this->qty, $this->discount_pct, $this->tax_rate);
    }
}
