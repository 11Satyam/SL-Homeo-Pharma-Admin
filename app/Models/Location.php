<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $guarded = ['id'];

    public function distributor() { return $this->belongsTo(Distributor::class); }

    public static function system(string $type): self
    {
        return static::firstOrCreate(['type' => $type, 'distributor_id' => null], ['name' => ucfirst($type)]);
    }
}
