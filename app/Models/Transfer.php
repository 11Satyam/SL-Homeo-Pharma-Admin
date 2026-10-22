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
}
