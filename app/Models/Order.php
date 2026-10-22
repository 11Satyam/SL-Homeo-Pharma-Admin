<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['delivered_at' => 'datetime'];
    public function lines() { return $this->hasMany(OrderLine::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
}
