<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $guarded = ['id'];

    /** Products are linked by name (products.category). */
    public function products() { return $this->hasMany(Product::class, 'category', 'name'); }
}
