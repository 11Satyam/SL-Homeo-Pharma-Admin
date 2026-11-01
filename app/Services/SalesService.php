<?php

namespace App\Services;

use App\Exceptions\InsufficientStock;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Posting rule: stock is RESERVED on order confirmation and DEDUCTED exactly
 * once at delivery (distributor -> customer). Invoice is issued at delivery
 * for the delivered quantity.
 */
class SalesService
{
    public function __construct(private StockService $stock) {}

    /** $extra: optional remarks / idempotency_key persisted on the order. */
    public function createOrder(Shop $shop, array $lines, ?int $staffId = null, array $extra = []): Order
    {
        return DB::transaction(function () use ($shop, $lines, $staffId, $extra) {
            $loc = $this->locationFor($shop->distributor_id);
            $o = Order::create([
                'number' => 'SO-'.now()->format('ymd').'-'.strtoupper(substr(uniqid(), -5)),
                'shop_id' => $shop->id, 'distributor_id' => $shop->distributor_id,
                'staff_id' => $staffId, 'created_by' => auth()->id(), 'status' => 'confirmed',
            ] + array_intersect_key($extra, array_flip(['remarks', 'idempotency_key'])));
            $total = '0';
            $short = [];
            foreach ($lines as $productId => $qty) {
                if ((int) $qty <= 0) {
                    continue;
                }
                $p = Product::where('active', true)->findOrFail($productId);
                try {
                    $this->stock->reserve($loc, $p->id, (int) $qty); // throws if oversold
                } catch (RuntimeException) {
                    // Keep checking the other lines so the user can fix them all in one go; the transaction rolls back.
                    $short[] = ['product_id' => $p->id, 'product' => $p->name, 'ordered' => (int) $qty, 'available' => max(0, $this->stock->available($loc, $p->id))];

                    continue;
                }
                // Price comes from the product master, never from the client.
                $o->lines()->create(['product_id' => $p->id, 'qty' => (int) $qty, 'price' => $p->selling_price, 'tax_rate' => $p->tax_rate]);
                $total = bcadd($total, $this->lineTotal($p->selling_price, $p->tax_rate, (int) $qty), 2);
            }
            if ($short) {
                throw new InsufficientStock($short);
            }
            if (! $o->lines()->exists()) {
                throw new RuntimeException('Add at least one product line.');
            }
            $o->update(['total' => $total]);
            AuditLog::record('order.created', $o);

            return $o;
        });
    }

    public function setStatus(Order $o, string $status): void
    {
        $allowed = ['confirmed' => ['packed'], 'packed' => ['out_for_delivery']];
        if (! in_array($status, $allowed[$o->status] ?? [], true)) {
            throw new RuntimeException("Cannot move order from {$o->status} to {$status}.");
        }
        $o->update(['status' => $status]);
        AuditLog::record('order.'.$status, $o);
    }

    /** $delivered: [line_id => qty]. Undelivered reservations are released. */
    public function deliver(Order $o, array $delivered): ?Invoice
    {
        return DB::transaction(function () use ($o, $delivered) {
            $o = Order::lockForUpdate()->findOrFail($o->id);
            if (! in_array($o->status, ['confirmed', 'packed', 'out_for_delivery'], true)) {
                throw new RuntimeException("Order is {$o->status}; cannot deliver.");
            }
            $loc = $this->locationFor($o->distributor_id);
            $customer = Location::system('customer');
            $total = '0';
            $any = false;
            $partial = false;
            foreach ($o->lines as $l) {
                $q = (int) ($delivered[$l->id] ?? 0);
                if ($q < 0 || $q > $l->qty) {
                    throw new RuntimeException('Delivered quantity out of range.');
                }
                $this->stock->release($loc, $l->product_id, $l->qty);
                if ($q > 0) {
                    $this->stock->move($l->product_id, $loc, $customer, $q, 'sale', "so-{$o->id}-{$l->id}", $o->number);
                    $l->update(['delivered_qty' => $q]);
                    $total = bcadd($total, $this->lineTotal($l->price, $l->tax_rate, $q), 2);
                    $any = true;
                }
                $partial = $partial || $q < $l->qty;
            }
            if (! $any) {
                throw new RuntimeException('Nothing delivered; use Failed Delivery instead.');
            }
            $o->update(['status' => $partial ? 'partially_delivered' : 'delivered', 'delivered_at' => now(), 'total' => $total]);
            $inv = Invoice::create([
                'number' => 'INV-'.now()->format('ymd').'-'.str_pad((string) $o->id, 5, '0', STR_PAD_LEFT),
                'order_id' => $o->id, 'shop_id' => $o->shop_id, 'distributor_id' => $o->distributor_id,
                'total' => $total, 'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($o->shop->payment_terms_days)->toDateString(),
            ]);
            AuditLog::record('order.delivered', $o, ['invoice' => $inv->number, 'total' => $total]);

            return $inv;
        });
    }

    /** Cancel or fail: release reservations, no stock movement was posted yet. */
    public function cancel(Order $o, string $status, ?string $reason = null): void
    {
        DB::transaction(function () use ($o, $status, $reason) {
            $o = Order::lockForUpdate()->findOrFail($o->id);
            if (! in_array($o->status, ['draft', 'confirmed', 'packed', 'out_for_delivery'], true)) {
                throw new RuntimeException("Order is {$o->status}; cannot {$status}.");
            }
            $loc = $this->locationFor($o->distributor_id);
            foreach ($o->lines as $l) {
                $this->stock->release($loc, $l->product_id, $l->qty);
            }
            $o->update(['status' => $status, 'failure_reason' => $reason]);
            AuditLog::record('order.'.$status, $o, ['reason' => $reason]);
        });
    }

    /** Shop return after inspection: sellable back to distributor, damaged to damage bin; credit note linked. */
    public function processReturn(Order $o, int $lineId, int $sellable, int $damaged, string $reason): CreditNote
    {
        return DB::transaction(function () use ($o, $lineId, $sellable, $damaged, $reason) {
            $l = $o->lines()->lockForUpdate()->findOrFail($lineId);
            $qty = $sellable + $damaged;
            if ($qty <= 0 || $l->returned_qty + $qty > $l->delivered_qty) {
                throw new RuntimeException('Return quantity exceeds delivered quantity.');
            }
            $customer = Location::system('customer');
            $ref = "ret-{$l->id}-{$l->returned_qty}";
            if ($sellable) {
                $this->stock->move($l->product_id, $customer, $this->locationFor($o->distributor_id), $sellable, 'return', $ref.'-s', $o->number, $reason);
            }
            if ($damaged) {
                $this->stock->move($l->product_id, $customer, Location::system('damaged'), $damaged, 'return_damaged', $ref.'-d', $o->number, $reason);
            }
            $l->update(['returned_qty' => $l->returned_qty + $qty]);
            $cn = CreditNote::create([
                'invoice_id' => $o->invoice->id, 'amount' => $this->lineTotal($l->price, $l->tax_rate, $qty),
                'reason' => $reason, 'stock_reference' => $ref,
            ]);
            AuditLog::record('return.processed', $o, compact('sellable', 'damaged', 'reason'));

            return $cn;
        });
    }

    /** $allocations: [invoice_id => amount]. Allocations cannot exceed the payment or any invoice's outstanding. */
    /** $extra: optional remarks / collected_at / idempotency_key persisted on the payment. */
    public function recordPayment(Shop $shop, string $amount, string $method, ?string $reference, array $allocations, array $extra = []): Payment
    {
        return DB::transaction(function () use ($shop, $amount, $method, $reference, $allocations, $extra) {
            if (bccomp($amount, '0', 2) <= 0) {
                throw new RuntimeException('Amount must be positive.');
            }
            $p = Payment::create([
                'shop_id' => $shop->id, 'distributor_id' => $shop->distributor_id, 'amount' => $amount,
                'method' => $method, 'reference' => $reference, 'collected_by' => auth()->id(), 'status' => 'collected',
                'collected_at' => $extra['collected_at'] ?? now(),
            ] + array_intersect_key($extra, array_flip(['remarks', 'idempotency_key'])));
            $p->update(['receipt_number' => 'RC-'.now()->format('ymd').'-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT)]);
            $sum = '0';
            foreach ($allocations as $invId => $amt) {
                if (bccomp((string) $amt, '0', 2) <= 0) {
                    continue;
                }
                $inv = Invoice::where('shop_id', $shop->id)->lockForUpdate()->findOrFail($invId);
                if (bccomp((string) $amt, $inv->outstanding(), 2) > 0) {
                    throw new RuntimeException("Allocation exceeds outstanding on {$inv->number}.");
                }
                $p->allocations()->create(['invoice_id' => $inv->id, 'amount' => $amt]);
                $sum = bcadd($sum, (string) $amt, 2);
            }
            if (bccomp($sum, $amount, 2) > 0) {
                throw new RuntimeException('Allocations exceed payment amount.');
            }
            AuditLog::record('payment.collected', $p, ['amount' => $amount, 'method' => $method]);

            return $p;
        });
    }

    public function verifyPayment(Payment $p): void
    {
        if ($p->status !== 'collected') {
            throw new RuntimeException('Payment already verified.');
        }
        $p->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
        AuditLog::record('payment.verified', $p);
    }

    public function locationFor(int $distributorId): Location
    {
        return Location::firstOrCreate(['type' => 'distributor', 'distributor_id' => $distributorId], ['name' => 'Distributor #'.$distributorId]);
    }

    /** Tax-inclusive line amount, rounded to 2dp; the one pricing formula for orders and invoices. */
    public function lineTotal($price, $taxRate, int $qty): string
    {
        $net = bcmul((string) $price, (string) $qty, 4);

        return bcadd($net, bcdiv(bcmul($net, (string) $taxRate, 4), '100', 4), 2);
    }
}
