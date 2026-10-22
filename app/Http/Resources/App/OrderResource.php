<?php

namespace App\Http\Resources\App;

use App\Services\SalesService;

/** Amounts are recomputed from stored line prices (fixed at order time), never from client input. */
class OrderResource extends ApiResource
{
    public function toArray($request): array
    {
        $sales = app(SalesService::class);
        $sub = $tax = $total = '0';
        $items = $this->lines->map(function ($l) use ($sales, &$sub, &$tax, &$total) {
            $net = bcmul((string) $l->price, (string) $l->qty, 2);
            $gross = $sales->lineTotal($l->price, $l->tax_rate, $l->qty);
            $sub = bcadd($sub, $net, 2);
            $tax = bcadd($tax, bcsub($gross, $net, 2), 2);
            $total = bcadd($total, $gross, 2);

            return [
                'id' => $l->id, 'product_id' => $l->product_id, 'name' => $l->product->name, 'sku' => $l->product->sku,
                'unit' => $l->product->unit, 'qty' => (int) $l->qty, 'delivered_qty' => (int) $l->delivered_qty,
                'price' => self::money($l->price), 'tax_rate' => self::money($l->tax_rate), 'discount' => '0.00',
                'subtotal' => $net, 'tax' => bcsub($gross, $net, 2), 'total' => $gross,
            ];
        });

        return [
            'id' => $this->id, 'number' => $this->number, 'status' => $this->status,
            'shop' => ['id' => $this->shop->id, 'name' => $this->shop->name],
            'staff' => $this->staff ? ['id' => $this->staff->id, 'name' => $this->staff->name] : null,
            'items' => $items,
            // No discount master exists yet; discounts are always zero and never accepted from the client.
            'subtotal' => $sub, 'discount_total' => '0.00', 'tax_total' => $tax, 'total' => $total,
            'invoice' => $this->invoice ? ['number' => $this->invoice->number, 'total' => self::money($this->invoice->total)] : null,
            'remarks' => $this->remarks, 'failure_reason' => $this->failure_reason,
            'created_at' => self::iso($this->created_at), 'delivered_at' => self::iso($this->delivered_at),
        ];
    }
}
