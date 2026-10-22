<?php

namespace App\Http\Resources\App;

class CollectionResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'receipt_number' => $this->receipt_number,
            'shop' => $this->shop ? ['id' => $this->shop->id, 'name' => $this->shop->name] : null,
            'amount' => self::money($this->amount), 'method' => $this->method, 'reference' => $this->reference,
            'remarks' => $this->remarks,
            // collected = with staff, awaiting deposit/verification; verified = reconciled by accounts.
            'status' => $this->status, 'verified' => $this->status === 'verified', 'verified_at' => self::iso($this->verified_at),
            'allocations' => $this->allocations->map(fn ($a) => ['invoice_id' => $a->invoice_id, 'amount' => self::money($a->amount)]),
            'collected_at' => self::iso($this->collected_at ?? $this->created_at), 'recorded_at' => self::iso($this->created_at),
        ];
    }
}
