<?php

namespace App\Http\Resources\App;

use App\Services\SalesService;

/** Expects an `available_qty` attribute selected from the staff member's distributor stock balance. */
class ProductResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'sku' => $this->sku, 'category' => $this->category,
            'unit' => $this->unit,
            'selling_price' => self::money($this->selling_price),
            'tax_rate' => self::money($this->tax_rate),
            'price_incl_tax' => app(SalesService::class)->lineTotal($this->selling_price, $this->tax_rate, 1),
            'available_qty' => max(0, (int) $this->available_qty),
            // Stock is quantity-tracked in this system; no product carries serial numbers.
            'tracking' => 'quantity',
        ];
    }
}
