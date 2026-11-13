<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shop extends Model
{
    protected $guarded = ['id'];

    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    /** Same name in the same area, or the same phone, under one distributor is almost always a double entry. */
    public static function duplicateOf(int $distributorId, string $name, ?string $area, ?string $phone): ?self
    {
        return static::where('distributor_id', $distributorId)->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->where(fn ($q) => $q->where(fn ($q) => $area !== null ? $q->whereRaw('LOWER(area) = ?', [Str::lower($area)]) : $q->whereNull('area'))
                ->orWhere(fn ($q) => $q->whereNotNull('phone')->where('phone', $phone ?? '')))
            ->first();
    }
}
