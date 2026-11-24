<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['dispatched_at' => 'datetime', 'received_at' => 'datetime'];
    public function lines() { return $this->hasMany(TransferLine::class); }
    public function from() { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function to() { return $this->belongsTo(Location::class, 'to_location_id'); }

    /** Transfer invoice number, derived from the transfer number (TR-… → TI-…). */
    public function invoiceNumber(): string
    {
        return preg_replace('/^TR-/', 'TI-', $this->number);
    }

    /** Sum of every line's amounts: gross, discount, taxable, tax, total. */
    public function totals(): array
    {
        $sum = ['gross' => 0.0, 'discount' => 0.0, 'taxable' => 0.0, 'tax' => 0.0, 'total' => 0.0];
        foreach ($this->lines as $l) {
            foreach ($l->totals() as $k => $v) {
                $sum[$k] = round($sum[$k] + $v, 2);
            }
        }

        return $sum;
    }
}
