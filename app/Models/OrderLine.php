<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLine extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;
    public function product() { return $this->belongsTo(Product::class); }
}
