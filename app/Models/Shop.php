<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $guarded = ['id'];

    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
