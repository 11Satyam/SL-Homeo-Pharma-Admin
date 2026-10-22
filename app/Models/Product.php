<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];
    public function balances() { return $this->hasMany(StockBalance::class); }
}
