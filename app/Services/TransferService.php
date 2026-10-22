<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Draft -> approved -> in_transit -> partially_received | received. */
class TransferService
{
    public function __construct(private StockService $stock) {}

    public function create(Location $from, Location $to, array $lines, ?string $remarks = null): Transfer
    {
        if ($from->id === $to->id) {
            throw new RuntimeException('Source and destination must differ.');
        }

        return DB::transaction(function () use ($from, $to, $lines, $remarks) {
            $t = Transfer::create([
                'number' => 'TR-'.now()->format('ymd').'-'.strtoupper(substr(uniqid(), -5)),
                'from_location_id' => $from->id, 'to_location_id' => $to->id,
                'remarks' => $remarks, 'created_by' => auth()->id(), 'status' => 'draft',
            ]);
            foreach ($lines as $productId => $qty) {
                if ((int) $qty > 0) {
                    $t->lines()->create(['product_id' => $productId, 'qty' => (int) $qty]);
                }
            }
            if (! $t->lines()->exists()) {
                throw new RuntimeException('Add at least one product line.');
            }
            AuditLog::record('transfer.created', $t);

            return $t;
        });
    }

    public function approve(Transfer $t): void
    {
        $this->expect($t, 'draft');
        $t->update(['status' => 'approved', 'approved_by' => auth()->id()]);
        AuditLog::record('transfer.approved', $t);
    }

    public function dispatch(Transfer $t): void
    {
        DB::transaction(function () use ($t) {
            $t = Transfer::lockForUpdate()->findOrFail($t->id);
            $this->expect($t, 'approved');
            $transit = Location::system('transit');
            foreach ($t->lines as $l) {
                $this->stock->move($l->product_id, $t->from, $transit, $l->qty, 'transfer_dispatch', "tr-{$t->id}-d-{$l->id}", $t->number);
            }
            $t->update(['status' => 'in_transit', 'dispatched_at' => now()]);
        });
    }

    /** $received / $damaged: [line_id => qty]. Unreceived quantity stays in transit. */
    public function receive(Transfer $t, array $received, array $damaged = []): void
    {
        DB::transaction(function () use ($t, $received, $damaged) {
            $t = Transfer::lockForUpdate()->findOrFail($t->id);
            if (! in_array($t->status, ['in_transit', 'partially_received'], true)) {
                throw new RuntimeException("Transfer is {$t->status}; cannot receive.");
            }
            $transit = Location::system('transit');
            $complete = true;
            foreach ($t->lines as $l) {
                $ok = max(0, (int) ($received[$l->id] ?? 0));
                $bad = max(0, (int) ($damaged[$l->id] ?? 0));
                if ($l->received_qty + $l->damaged_qty + $ok + $bad > $l->qty) {
                    throw new RuntimeException('Received quantity exceeds dispatched quantity.');
                }
                $seq = $l->received_qty + $l->damaged_qty;
                if ($ok) {
                    $this->stock->move($l->product_id, $transit, $t->to, $ok, 'transfer_receive', "tr-{$t->id}-r-{$l->id}-{$seq}", $t->number);
                }
                if ($bad) {
                    $this->stock->move($l->product_id, $transit, Location::system('damaged'), $bad, 'transfer_damage', "tr-{$t->id}-x-{$l->id}-{$seq}", $t->number, 'Damaged in transit');
                }
                $l->update(['received_qty' => $l->received_qty + $ok, 'damaged_qty' => $l->damaged_qty + $bad]);
                $complete = $complete && ($l->received_qty + $l->damaged_qty === $l->qty);
            }
            $t->update(['status' => $complete ? 'received' : 'partially_received', 'received_at' => now()]);
            AuditLog::record('transfer.received', $t, compact('received', 'damaged'));
        });
    }

    private function expect(Transfer $t, string $status): void
    {
        if ($t->status !== $status) {
            throw new RuntimeException("Transfer is {$t->status}; expected {$status}.");
        }
    }
}
