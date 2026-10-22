<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    public function product() { return $this->belongsTo(Product::class); }
    public function from() { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function to() { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
