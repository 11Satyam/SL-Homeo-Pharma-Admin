<?php

namespace App\Http\Resources\App;

class ShopResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'owner' => $this->owner, 'phone' => $this->phone,
            'area' => $this->area, 'address' => $this->address,
            'credit_limit' => self::money($this->credit_limit), 'payment_terms_days' => (int) $this->payment_terms_days,
        ];
    }
}
