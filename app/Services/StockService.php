<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The stock ledger is the single source of truth. Every quantity change is a
 * movement from one location to another; balances are a cached projection
 * updated in the same DB transaction. External/customer/damaged/missing/adjustment locations are
 * sinks/sources and may go negative; internal locations never can.
 */
class StockService
{
    public const SINKS = ['external', 'customer', 'damaged', 'missing', 'adjustment'];

    public function move(int $productId, Location $from, Location $to, int $qty, string $type,
        string $key, ?string $reference = null, ?string $reason = null): StockMovement
    {
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($productId, $from, $to, $qty, $type, $key, $reference, $reason) {
            // Idempotency: the same key never posts twice.
            if ($existing = StockMovement::where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $this->adjust($from, $productId, -$qty);
            $this->adjust($to, $productId, $qty);

            $m = StockMovement::create([
                'idempotency_key' => $key, 'product_id' => $productId, 'qty' => $qty,
                'from_location_id' => $from->id, 'to_location_id' => $to->id, 'type' => $type,
                'reference' => $reference, 'reason' => $reason, 'user_id' => auth()->id(),
            ]);
            AuditLog::record('stock.'.$type, $m, ['qty' => $qty, 'from' => $from->name, 'to' => $to->name, 'ref' => $reference]);

            return $m;
        });
    }

    /** Correct a posted movement with an equal and opposite entry; never edit/delete. */
    public function reverse(StockMovement $m, string $reason): StockMovement
    {
        $r = $this->move($m->product_id, $m->to, $m->from, $m->qty, 'reversal', 'rev-'.$m->id, $m->reference, $reason);
        $r->update(['reverses_id' => $m->id]);

        return $r;
    }

    public function available(Location $loc, int $productId): int
    {
        $b = StockBalance::where('location_id', $loc->id)->where('product_id', $productId)->first();

        return $b ? $b->qty - $b->reserved : 0;
    }

    public function reserve(Location $loc, int $productId, int $qty): void
    {
        // Atomic conditional update prevents concurrent overselling.
        $ok = StockBalance::where('location_id', $loc->id)->where('product_id', $productId)
            ->whereRaw('qty - reserved >= ?', [$qty])->increment('reserved', $qty);
        if (! $ok) {
            throw new RuntimeException('Insufficient available stock.');
        }
    }

    public function release(Location $loc, int $productId, int $qty): void
    {
        StockBalance::where('location_id', $loc->id)->where('product_id', $productId)
            ->where('reserved', '>=', $qty)->decrement('reserved', $qty);
    }

    private function adjust(Location $loc, int $productId, int $delta): void
    {
        StockBalance::firstOrCreate(['location_id' => $loc->id, 'product_id' => $productId]);
        $q = StockBalance::where('location_id', $loc->id)->where('product_id', $productId);
        if ($delta < 0 && ! in_array($loc->type, self::SINKS, true)) {
            // Only reserved-free stock may leave, unless the caller released it first.
            $q->whereRaw('qty - reserved >= ?', [-$delta]);
        }
        if (! $q->increment('qty', $delta)) {
            throw new RuntimeException("Insufficient stock at {$loc->name}.");
        }
    }

    /** Recompute balances from ledger; returns mismatches (should be empty). */
    public function reconcile(): array
    {
        $ledger = [];
        foreach (StockMovement::all() as $m) {
            $ledger[$m->from_location_id][$m->product_id] = ($ledger[$m->from_location_id][$m->product_id] ?? 0) - $m->qty;
            $ledger[$m->to_location_id][$m->product_id] = ($ledger[$m->to_location_id][$m->product_id] ?? 0) + $m->qty;
        }
        $bad = [];
        foreach (StockBalance::all() as $b) {
            $exp = $ledger[$b->location_id][$b->product_id] ?? 0;
            if ($exp !== (int) $b->qty) {
                $bad[] = ['location' => $b->location_id, 'product' => $b->product_id, 'ledger' => $exp, 'balance' => $b->qty];
            }
        }

        return $bad;
    }
}
