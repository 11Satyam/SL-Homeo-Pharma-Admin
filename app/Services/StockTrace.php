<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Support\Fmt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only view over the stock ledger for one distributor. It stores nothing: every figure is derived from
 * stock_movements and stock_balances, so it cannot drift from what StockService posted.
 */
class StockTrace
{
    public const TYPES = [
        'opening' => 'Opening', 'receipt' => 'Receipt', 'transfer_dispatch' => 'Transfer dispatch', 'transfer_receive' => 'Transfer receipt',
        'transfer_damage' => 'Transit damage', 'sale' => 'Sale (delivery)', 'return' => 'Return to stock', 'return_damaged' => 'Return (damaged)',
        'damage_adjustment' => 'Write-off', 'stock_missing' => 'Missing stock', 'stock_adjustment' => 'Stock adjustment', 'reversal' => 'Reversal',
    ];

    public const KINDS = [
        'in' => ['Stock In', 'info', ['opening', 'receipt']],
        'out' => ['Stock Out', 'ok', ['sale']],
        'transfer' => ['Transfer', 'violet', ['transfer_dispatch', 'transfer_receive']],
        'return' => ['Return', 'warn', ['return', 'return_damaged']],
        'adjustment' => ['Adjustment', 'bad', ['transfer_damage', 'damage_adjustment', 'stock_missing', 'stock_adjustment', 'reversal']],
    ];

    private const BLANK = ['allocated' => 0, 'sent' => 0, 'sold' => 0, 'returned' => 0, 'damaged' => 0, 'missing' => 0, 'adjusted' => 0,
        'transit_in' => 0, 'transit_out' => 0, 'on_hand' => 0, 'reserved' => 0, 'available' => 0];

    /** A distributor's stock location; imported distributors may not have one yet. */
    public function location(Distributor $d): Location
    {
        return Location::firstOrCreate(['type' => 'distributor', 'distributor_id' => $d->id], ['name' => $d->business_name]);
    }

    /**
     * Movements at the location, plus the legs that never touch it but belong to its documents: a dispatch into
     * transit, transit damage, and damaged shop returns.
     */
    public function related(Distributor $d, Location $loc): Builder
    {
        return StockMovement::query()->where(fn ($q) => $q
            ->where('stock_movements.from_location_id', $loc->id)
            ->orWhere('stock_movements.to_location_id', $loc->id)
            ->orWhereIn('stock_movements.reference', Transfer::select('number')
                ->where(fn ($t) => $t->where('from_location_id', $loc->id)->orWhere('to_location_id', $loc->id)))
            ->orWhereIn('stock_movements.reference', Order::select('number')->where('distributor_id', $d->id)));
    }

    /**
     * Totals and per-product figures. A reversal subtracts from the bucket of the entry it undoes. $from and $to
     * limit the flow figures only; on hand, reserved and in transit are always as of now.
     */
    public function metrics(Distributor $d, ?string $from = null, ?string $to = null): array
    {
        $loc = $this->location($d);
        $here = (int) $loc->id;
        $inbound = Transfer::where('to_location_id', $here)->pluck('id', 'number');
        $outbound = Transfer::where('from_location_id', $here)->pluck('id', 'number');
        $rows = $this->related($d, $loc)->leftJoin('stock_movements as orig', 'orig.id', '=', 'stock_movements.reverses_id')
            ->get(['stock_movements.product_id', 'stock_movements.type', 'stock_movements.qty', 'stock_movements.from_location_id',
                'stock_movements.to_location_id', 'stock_movements.reference', 'stock_movements.created_at', 'orig.type as orig_type']);

        $products = [];
        $ledger = [];
        foreach ($rows as $r) {
            $pid = (int) $r->product_id;
            $qty = (int) $r->qty;
            $rowFrom = (int) $r->from_location_id;
            $rowTo = (int) $r->to_location_id;
            $products[$pid] ??= self::BLANK;
            $ledger[$pid] = ($ledger[$pid] ?? 0) + ($rowTo === $here ? $qty : 0) - ($rowFrom === $here ? $qty : 0);

            $reversal = $r->type === 'reversal';
            $type = $reversal ? $r->orig_type : $r->type;
            $n = $reversal ? -$qty : $qty;
            [$src, $dst] = $reversal ? [$rowTo, $rowFrom] : [$rowFrom, $rowTo];
            $isIn = isset($inbound[$r->reference]);
            $isOut = isset($outbound[$r->reference]);

            $transit = match ($type) { 'transfer_dispatch' => $n, 'transfer_receive', 'transfer_damage' => -$n, default => 0 };
            $products[$pid]['transit_in'] += $isIn ? $transit : 0;
            $products[$pid]['transit_out'] += $isOut ? $transit : 0;

            $day = $r->created_at->toDateString();
            if (($from && $day < $from) || ($to && $day > $to)) {
                continue;
            }
            foreach ($this->buckets($type, $n, $src, $dst, $here, $isIn, $isOut) as $bucket => $value) {
                $products[$pid][$bucket] += $value;
            }
        }

        foreach (StockBalance::where('location_id', $here)->get() as $b) {
            $products[$b->product_id] ??= self::BLANK;
            $products[$b->product_id]['on_hand'] = (int) $b->qty;
            $products[$b->product_id]['reserved'] = (int) $b->reserved;
            $products[$b->product_id]['available'] = (int) $b->qty - (int) $b->reserved;
        }

        $reconciled = collect($products)->every(fn ($p, $pid) => ($ledger[$pid] ?? 0) === $p['on_hand']);
        $names = Product::whereIn('id', array_keys($products))->get()->keyBy('id');
        $list = collect($products)->filter(fn ($p) => collect($p)->contains(fn ($v) => $v !== 0))
            ->map(fn ($p, $pid) => $p + ['product' => $names[$pid]])->sortBy(fn ($p) => $p['product']->name)->values();
        $totals = collect(array_keys(self::BLANK))->mapWithKeys(fn ($k) => [$k => $list->sum($k)])->all();

        return ['totals' => $totals, 'products' => $list, 'reconciled' => $reconciled, 'location' => $loc];
    }

    /** Ledger rows ready to display. With $loc, each row also carries the signed quantity and the balance left there. */
    public function decorate(Collection $movements, ?Location $loc = null): Collection
    {
        $refs = $movements->pluck('reference')->filter()->unique()->values();
        $transfers = Transfer::with('from', 'to')->whereIn('number', $refs)->get()->keyBy('number');
        $orders = Order::with('shop', 'invoice')->whereIn('number', $refs)->get()->keyBy('number');
        $reversed = StockMovement::whereIn('reverses_id', $movements->pluck('id'))->pluck('reverses_id')->flip();
        $remaining = $loc ? $this->remaining($loc, $movements) : [];

        return $movements->map(function (StockMovement $m) use ($transfers, $orders, $reversed, $remaining, $loc) {
            $transfer = $transfers[$m->reference] ?? null;
            $order = $orders[$m->reference] ?? null;
            $kind = collect(self::KINDS)->search(fn ($k) => in_array($m->type, $k[2], true)) ?: 'adjustment';
            [$statusTone, $statusLabel] = match (true) {
                $m->type === 'reversal' => ['neutral', 'Reversal'],
                isset($reversed[$m->id]) => ['bad', 'Reversed'],
                (bool) $transfer => Fmt::status($transfer->status),
                (bool) $order => Fmt::status($order->status),
                default => ['ok', 'Posted'],
            };
            $destination = $this->place($m->to, $order);
            $signed = null;
            if ($loc && (int) $m->to_location_id === (int) $loc->id) {
                $signed = (int) $m->qty;
            } elseif ($loc && (int) $m->from_location_id === (int) $loc->id) {
                $signed = -(int) $m->qty;
            }

            return (object) [
                'm' => $m, 'kind' => $kind, 'kindLabel' => self::KINDS[$kind][0], 'kindTone' => self::KINDS[$kind][1],
                'typeLabel' => self::TYPES[$m->type] ?? Fmt::label($m->type),
                'source' => $this->place($m->from, $order), 'destination' => $destination,
                'signed' => $signed, 'remaining' => $remaining[$m->id] ?? null,
                'reference' => $m->reference ?: '#'.$m->id,
                'invoice' => $order?->invoice, 'order' => $order, 'transfer' => $transfer,
                'dispatchedAt' => $transfer?->dispatched_at, 'deliveredAt' => $transfer?->received_at ?? $order?->delivered_at,
                'statusTone' => $statusTone, 'statusLabel' => $statusLabel,
                'currentLocation' => match (true) {
                    isset($reversed[$m->id]) => $this->place($m->from, $order),
                    $transfer && in_array($transfer->status, ['in_transit', 'partially_received'], true) && $m->type === 'transfer_dispatch' => 'In transit',
                    default => $destination,
                },
            ];
        });
    }

    /** Every entry for the same document and product, oldest first, with any reversal of this entry. */
    public function chain(StockMovement $m): Collection
    {
        return StockMovement::with('product', 'from', 'to', 'user')->where(fn ($q) => $q->where('id', $m->id)->orWhere('reverses_id', $m->id)
            ->when($m->reverses_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->when($m->reference, fn ($q, $ref) => $q->orWhere(fn ($q) => $q->where('reference', $ref)->where('product_id', $m->product_id))))
            ->orderBy('id')->get();
    }

    /** Where a product sits right now across the whole network. External is the supplier side, so it is left out. */
    public function network(int $productId): Collection
    {
        $order = ['warehouse', 'transit', 'distributor', 'customer', 'damaged', 'missing', 'adjustment'];

        return StockBalance::with('location')->where('product_id', $productId)->where('qty', '!=', 0)
            ->whereHas('location', fn ($q) => $q->where('type', '!=', 'external'))->get()
            ->sortBy(fn ($b) => [array_search($b->location->type, $order), $b->location->name])->values();
    }

    /** The balance at one location immediately after a movement. */
    public function balanceAfter(Location $loc, StockMovement $m): int
    {
        $sum = fn (string $col) => (int) StockMovement::where('product_id', $m->product_id)->where($col, $loc->id)->where('id', '<=', $m->id)->sum('qty');

        return $sum('to_location_id') - $sum('from_location_id');
    }

    public static function auditSummary(?array $data): string
    {
        return collect($data ?? [])->filter(fn ($v) => $v !== null && $v !== [] && $v !== '')
            ->map(fn ($v, $k) => Fmt::label((string) $k).': '.(is_scalar($v) ? $v : json_encode($v)))->implode(' · ');
    }

    /** Which report figures one ledger entry adds to. Damaged shop returns count as both returned and damaged. */
    private function buckets(string $type, int $n, int $src, int $dst, int $here, bool $isIn, bool $isOut): array
    {
        return match (true) {
            in_array($type, ['opening', 'receipt'], true) && $dst === $here => ['allocated' => $n],
            $type === 'transfer_dispatch' && $isIn => ['allocated' => $n],
            $type === 'transfer_dispatch' && $isOut => ['sent' => $n],
            $type === 'sale' => ['sold' => $n],
            $type === 'return' => ['returned' => $n],
            $type === 'return_damaged' => ['returned' => $n, 'damaged' => $n],
            $type === 'transfer_damage' && $isIn => ['damaged' => $n],
            $type === 'damage_adjustment' && $src === $here => ['damaged' => $n],
            $type === 'stock_missing' && $src === $here => ['missing' => $n],
            $type === 'stock_adjustment' => ['adjusted' => $dst === $here ? $n : -$n],
            default => [],
        };
    }

    /** Running balance per movement id, for the products in $movements. */
    private function remaining(Location $loc, Collection $movements): array
    {
        $here = (int) $loc->id;
        $balance = [];
        $after = [];
        StockMovement::whereIn('product_id', $movements->pluck('product_id')->unique())
            ->where(fn ($q) => $q->where('from_location_id', $here)->orWhere('to_location_id', $here))
            ->orderBy('id')->get(['id', 'product_id', 'qty', 'from_location_id', 'to_location_id'])
            ->each(function ($m) use ($here, &$balance, &$after) {
                $balance[$m->product_id] = ($balance[$m->product_id] ?? 0) + ((int) $m->to_location_id === $here ? (int) $m->qty : -(int) $m->qty);
                $after[$m->id] = $balance[$m->product_id];
            });

        return $after;
    }

    /** Human name for a ledger location; the shared customer location becomes the shop on the order. */
    private function place(Location $l, ?Order $order): string
    {
        return match ($l->type) {
            'customer' => $order?->shop?->name ?? 'Customer',
            'external' => 'Supplier / external',
            'transit' => 'In transit',
            default => $l->name,
        };
    }
}
