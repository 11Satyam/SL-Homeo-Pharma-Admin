<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'geofence_radius_m' => 'integer'];
    public function shops() { return $this->hasMany(Shop::class); }
    public function users() { return $this->hasMany(User::class); }
    public function location() { return $this->hasOne(Location::class)->where('type', 'distributor'); }

    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->geofence_radius_m > 0;
    }
}
