<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];

    public const METHODS = ['cash', 'upi', 'bank_transfer', 'cheque', 'other'];

    protected $casts = ['verified_at' => 'datetime', 'collected_at' => 'datetime'];
    public function shop() { return $this->belongsTo(Shop::class); }
    public function collector() { return $this->belongsTo(User::class, 'collected_by'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
}
