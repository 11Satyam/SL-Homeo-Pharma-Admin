<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StockService;
use App\Services\StockTrace;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin distributor details and stock tracing. Reads come from StockTrace; every stock change goes through StockService. */
class DistributorStockController extends Controller
{
    public function __construct(private StockTrace $trace, private StockService $stock) {}

    public function show(Request $r, Distributor $distributor)
    {
        $tab = $r->validate(['tab' => 'nullable|in:overview,movements,transfers,returns,activity'] + $this->filterRules())['tab'] ?? 'overview';
        $metrics = $this->trace->metrics($distributor);
        $loc = $metrics['location'];
        $data = ['distributor' => $distributor->loadCount('shops')->load('users'), 'tab' => $tab, 'loc' => $loc, 'metrics' => $metrics,
            'products' => Product::orderBy('name')->get(),
            'free' => StockBalance::where('location_id', $loc->id)->get()->mapWithKeys(fn ($b) => [$b->product_id => $b->qty - $b->reserved]),
            'firstWarehouse' => Location::where('type', 'warehouse')->whereNull('archived_at')->orderBy('name')->first()];

        if ($tab === 'movements') {
            $page = $this->movements($r, $distributor, $loc)->paginate(25)->withQueryString();
            $data['rows'] = $page->setCollection($this->trace->decorate($page->getCollection(), $loc));
            $data['network'] = $r->product_id ? $this->trace->network((int) $r->product_id) : collect();
        } elseif ($tab === 'transfers') {
            $data['transfers'] = Transfer::with('from', 'to', 'lines.product')
                ->where(fn ($q) => $q->where('from_location_id', $loc->id)->orWhere('to_location_id', $loc->id))
                ->latest('id')->paginate(25)->withQueryString();
        } elseif ($tab === 'returns') {
            $data['orders'] = Order::with('shop', 'invoice', 'lines.product')->where('distributor_id', $distributor->id)->whereHas('invoice')
                ->whereHas('lines', fn ($q) => $q->whereColumn('delivered_qty', '>', 'returned_qty'))->latest('id')->paginate(15)->withQueryString();
        } elseif ($tab === 'activity') {
            $data['logs'] = $this->activity($r, $distributor, $loc)->paginate(30)->withQueryString();
        }

        return view('erp.distributor', $data);
    }

    /** Record damaged or missing stock, or correct a count. The reason is mandatory and the ledger entry permanent. */
    public function operate(Request $r, Distributor $distributor)
    {
        $d = $r->validate([
            'kind' => 'required|in:damaged,missing,increase,decrease', 'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1', 'reason' => 'required|string|max:255', 'key' => 'required|string|max:64',
        ], ['reason.required' => 'Write why this stock is changing, for example "Broken in storage" or "Physical count on 5 Nov".']);
        $loc = $this->trace->location($distributor);
        $product = Product::findOrFail($d['product_id']);
        if ($d['kind'] !== 'increase' && $d['qty'] > ($free = $this->stock->available($loc, $product->id))) {
            throw ValidationException::withMessages(['qty' => "Only {$free} {$product->unit} of {$product->name} is free at {$loc->name} (stock reserved for orders cannot be removed)."]);
        }
        [$type, $from, $to, $word] = match ($d['kind']) {
            'damaged' => ['damage_adjustment', $loc, Location::system('damaged'), 'written off as damaged'],
            'missing' => ['stock_missing', $loc, Location::system('missing'), 'recorded as missing'],
            'increase' => ['stock_adjustment', Location::system('adjustment'), $loc, 'added by adjustment'],
            'decrease' => ['stock_adjustment', $loc, Location::system('adjustment'), 'removed by adjustment'],
        };
        $ref = 'ADJ-'.now()->format('ymd').'-'.strtoupper(substr(uniqid(), -5));
        $m = $this->stock->move($product->id, $from, $to, (int) $d['qty'], $type, 'op-'.$d['key'], $ref, $d['reason']);

        return back()->with('status', $m->wasRecentlyCreated
            ? "{$d['qty']} {$product->unit} of {$product->name} {$word}. Reference {$m->reference}. Free at {$loc->name} now: ".$this->stock->available($loc, $product->id).'.'
            : 'This entry was already saved, so it was not posted twice.');
    }

    public function movement(StockMovement $movement)
    {
        $movement->load('product', 'from', 'to', 'user');
        $row = $this->trace->decorate(collect([$movement]))->first();
        $transfer = $row->transfer;

        return view('erp.movement', ['row' => $row,
            'balances' => collect([$movement->from, $movement->to])->filter(fn ($l) => in_array($l->type, ['warehouse', 'distributor'], true))
                ->map(fn ($l) => ['name' => $l->name, 'qty' => $this->trace->balanceAfter($l, $movement)]),
            'chain' => $this->trace->decorate($this->trace->chain($movement)),
            'network' => $this->trace->network($movement->product_id),
            'people' => $transfer ? User::whereIn('id', array_filter([$transfer->created_by, $transfer->approved_by]))->pluck('name', 'id') : collect(),
            'logs' => AuditLog::with('user')->where('entity', 'StockMovement')->where('entity_id', $movement->id)->latest('id')->get(),
            'distributorId' => collect([$movement->from, $movement->to])->pluck('distributor_id')->filter()->first()]);
    }

    /** All distributors side by side. */
    public function report(Request $r)
    {
        $f = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'format' => 'nullable|in:xlsx,pdf']);
        $rows = Distributor::orderBy('business_name')->get()->map(fn ($d) => ['distributor' => $d] + $this->trace->metrics($d, $f['from'] ?? null, $f['to'] ?? null));
        if ($format = $f['format'] ?? null) {
            $headers = ['Distributor', 'Territory', 'Status', 'Allocated', 'On hand', 'Reserved', 'Available', 'Sold', 'In transit', 'Returned', 'Damaged', 'Missing'];
            $table = $rows->map(function ($x) {
                $t = $x['totals'];

                return [$x['distributor']->business_name, (string) $x['distributor']->area, ucfirst($x['distributor']->status), $t['allocated'], $t['on_hand'],
                    $t['reserved'], $t['available'], $t['sold'], $t['transit_in'], $t['returned'], $t['damaged'], $t['missing']];
            })->all();

            return $this->output($format, 'Distributor stock report', 'distributor-stock-report', $headers, $table, $this->range($f));
        }

        return view('erp.distributor-report', ['rows' => $rows]);
    }

    public function export(Request $r, Distributor $distributor)
    {
        $f = $r->validate(['report' => 'required|in:products,movements,activity', 'format' => 'required|in:xlsx,pdf'] + $this->filterRules());
        $loc = $this->trace->location($distributor);
        [$title, $headers, $rows] = match ($f['report']) {
            'products' => $this->productTable($distributor),
            'movements' => $this->movementTable($r, $distributor, $loc),
            'activity' => $this->activityTable($r, $distributor, $loc),
        };

        return $this->output($f['format'], "{$title} · {$distributor->business_name}", Str::slug($distributor->business_name.'-'.$f['report']), $headers, $rows, $this->range($f));
    }

    private function filterRules(): array
    {
        return ['q' => 'nullable|string|max:100', 'product_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'kind' => ['nullable', Rule::in(array_keys(StockTrace::KINDS))]];
    }

    private function movements(Request $r, Distributor $d, Location $loc)
    {
        return $this->trace->related($d, $loc)->with('product', 'from', 'to', 'user')->orderByDesc('stock_movements.id')
            ->when($r->q, fn ($q, $s) => $q->where(fn ($q) => $q->where('stock_movements.reference', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"))))
            ->when($r->product_id, fn ($q, $v) => $q->where('stock_movements.product_id', $v))
            ->when($r->kind, fn ($q, $v) => $q->whereIn('stock_movements.type', StockTrace::KINDS[$v][2]))
            ->when($r->from, fn ($q, $v) => $q->whereDate('stock_movements.created_at', '>=', $v))
            ->when($r->to, fn ($q, $v) => $q->whereDate('stock_movements.created_at', '<=', $v));
    }

    /** Audit entries for this distributor, its stock movements, its transfers and its orders. */
    private function activity(Request $r, Distributor $d, Location $loc)
    {
        $of = fn (string $entity, $ids) => fn ($q) => $q->where('entity', $entity)->whereIn('entity_id', $ids);

        return AuditLog::with('user')->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('entity', 'Distributor')->where('entity_id', $d->id))
            ->orWhere($of('StockMovement', $this->trace->related($d, $loc)->select('stock_movements.id')))
            ->orWhere($of('Transfer', Transfer::select('id')->where(fn ($t) => $t->where('from_location_id', $loc->id)->orWhere('to_location_id', $loc->id))))
            ->orWhere($of('Order', Order::select('id')->where('distributor_id', $d->id))))
            ->latest('id')
            ->when($r->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($r->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    private function productTable(Distributor $d): array
    {
        $rows = $this->trace->metrics($d)['products']->map(fn ($p) => [$p['product']->name, $p['product']->sku, $p['allocated'], $p['sent'], $p['sold'], $p['returned'],
            $p['damaged'], $p['missing'], $p['adjusted'], $p['transit_in'], $p['on_hand'], $p['reserved'], $p['available']])->all();

        return ['Product stock report', ['Product', 'SKU', 'Allocated', 'Sent out', 'Sold', 'Returned', 'Damaged', 'Missing', 'Adjusted', 'In transit', 'On hand', 'Reserved', 'Available'], $rows];
    }

    private function movementTable(Request $r, Distributor $d, Location $loc): array
    {
        $rows = $this->trace->decorate($this->movements($r, $d, $loc)->get(), $loc)->map(fn ($x) => [
            $x->m->created_at->format('Y-m-d H:i'), $x->m->product->name, $x->m->product->sku, $x->kindLabel, $x->typeLabel, $x->source, $x->destination,
            $x->signed ?? (int) $x->m->qty, $x->remaining ?? '', $x->reference, $x->invoice?->number ?? '', (string) $x->m->reason, $x->m->user?->name ?? 'System', $x->statusLabel,
        ])->all();

        return ['Stock movement history', ['Date', 'Product', 'SKU', 'Movement', 'Type', 'Source', 'Destination', 'Qty', 'Remaining', 'Reference', 'Invoice', 'Reason', 'Handled by', 'Status'], $rows];
    }

    private function activityTable(Request $r, Distributor $d, Location $loc): array
    {
        $rows = $this->activity($r, $d, $loc)->get()->map(fn ($l) => [$l->created_at->format('Y-m-d H:i'), $l->user?->name ?? 'System', $l->action,
            trim($l->entity.' #'.$l->entity_id), StockTrace::auditSummary($l->data), (string) $l->ip])->all();

        return ['Audit trail', ['Date', 'User', 'Action', 'Record', 'Details', 'IP'], $rows];
    }

    private function range(array $f): ?string
    {
        return ($f['from'] ?? null) || ($f['to'] ?? null) ? ($f['from'] ?? 'Start').' to '.($f['to'] ?? 'today') : null;
    }

    private function output(string $format, string $title, string $file, array $headers, array $rows, ?string $range)
    {
        return $format === 'xlsx' ? Xlsx::download($file, $headers, $rows)
            : view('erp.stock-print', compact('title', 'headers', 'rows', 'range'));
    }
}
