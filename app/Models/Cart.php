<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A field staff member's working basket for one shop; becomes an Order at checkout. */
class Cart extends Model
{
    protected $guarded = ['id'];

    public function items() { return $this->hasMany(CartItem::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function user() { return $this->belongsTo(User::class); }
}
