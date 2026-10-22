<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per staff member per business day. Coordinates are device-reported, never "verified". */
class Attendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime', 'check_in_client_at' => 'datetime', 'check_in_within_geofence' => 'boolean',
        'check_out_at' => 'datetime', 'check_out_client_at' => 'datetime', 'check_out_within_geofence' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function distributor() { return $this->belongsTo(Distributor::class); }
}
