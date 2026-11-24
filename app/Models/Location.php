<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['archived_at' => 'datetime'];

    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function balances() { return $this->hasMany(StockBalance::class); }

    /**
     * The warehouse every transfer is sent from: the active warehouse named "Main Warehouse", or, if none is
     * named that, the oldest active warehouse. Null when no warehouse exists yet.
     */
    public static function mainWarehouse(): ?self
    {
        return static::where('type', 'warehouse')->whereNull('archived_at')
            ->orderByRaw("CASE WHEN LOWER(name) = 'main warehouse' THEN 0 ELSE 1 END")->orderBy('id')->first();
    }

    public static function system(string $type): self
    {
        return static::firstOrCreate(['type' => $type, 'distributor_id' => null], ['name' => ucfirst($type)]);
    }
}
