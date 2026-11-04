<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['archived_at' => 'datetime'];

    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function balances() { return $this->hasMany(StockBalance::class); }

    public static function system(string $type): self
    {
        return static::firstOrCreate(['type' => $type, 'distributor_id' => null], ['name' => ucfirst($type)]);
    }
}
