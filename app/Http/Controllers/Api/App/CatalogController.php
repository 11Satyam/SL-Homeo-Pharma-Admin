<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\ProductResource;
use App\Http\Resources\App\ShopResource;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Product;
use App\Models\Shop;
use App\Support\BusinessDay;
use Illuminate\Http\Request;

/** Parties (shops) and products for order / collection forms. Stock comes from the shared ledger balances. */
class CatalogController extends FieldController
{
    public function parties(Request $r)
    {
        $p = $r->validate(['search' => 'nullable|string|max:100', 'area' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:100']);
        $q = Shop::where('distributor_id', $this->did())->where('status', 'active')
            ->when($p['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($s))
                ->orWhere('owner', 'like', $this->like($s))->orWhere('phone', 'like', $this->like($s))))
            ->when($p['area'] ?? null, fn ($q, $a) => $q->where('area', $a))
            ->orderBy('name');

        return ShopResource::collection($q->paginate($p['per_page'] ?? 50)->withQueryString());
    }

    /**
     * Add a shop for the caller's distributor. Credit limit and payment terms stay at the distributor's defaults:
     * only the distributor can change them from the Admin Panel. A shop that already exists is returned with 409
     * instead of being added twice, so a network retry is safe without an Idempotency-Key.
     */
    public function storeParty(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:255', 'owner' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30',
            'area' => 'nullable|string|max:100', 'address' => 'nullable|string|max:500',
        ]);
        if ($dupe = Shop::duplicateOf($this->did(), $d['name'], $d['area'] ?? null, $d['phone'] ?? null)) {
            return (new ShopResource($dupe))->additional([
                'message' => "\"{$dupe->name}\"".($dupe->area ? " in {$dupe->area}" : '').' already exists.'
                    .($dupe->status === 'active' ? ' Use it instead of adding it again.' : ' It is inactive: ask your distributor to activate it.'),
                'code' => 'duplicate_shop',
            ])->response()->setStatusCode(409);
        }
        $shop = Shop::create(array_filter($d, fn ($v) => $v !== null) + ['distributor_id' => $this->did()]);
        AuditLog::record('shop.created', $shop, ['source' => 'field_app']);

        return (new ShopResource($shop->fresh()))->response()->setStatusCode(201);
    }

    /** Open invoices for a shop, for allocating a collection. */
    public function partyInvoices(int $shop)
    {
        $s = $this->ownShop($shop);
        $rows = $s->invoices()->with('allocations', 'creditNotes')->orderBy('due_date')->get()
            ->map(fn ($i) => ['id' => $i->id, 'number' => $i->number, 'issue_date' => $i->issue_date->toDateString(),
                'due_date' => $i->due_date->toDateString(), 'total' => number_format((float) $i->total, 2, '.', ''),
                'outstanding' => $i->outstanding(), 'overdue' => $i->due_date->toDateString() < BusinessDay::today()])
            ->filter(fn ($i) => bccomp($i['outstanding'], '0', 2) > 0)->values();

        return response()->json(['data' => $rows, 'meta' => [
            'shop' => ['id' => $s->id, 'name' => $s->name],
            'outstanding_total' => $rows->reduce(fn ($c, $i) => bcadd($c, $i['outstanding'], 2), '0.00'),
        ]]);
    }

    public function products(Request $r)
    {
        $p = $r->validate([
            'search' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100',
            'in_stock' => 'nullable|boolean', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $q = $this->productQuery()
            ->when($p['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('products.name', 'like', $this->like($s))
                ->orWhere('products.sku', 'like', $this->like($s))))
            ->when($p['category'] ?? null, fn ($q, $c) => $q->where('products.category', $c))
            ->when($r->boolean('in_stock'), fn ($q) => $q->whereRaw('COALESCE(sb.qty - sb.reserved, 0) > 0'))
            ->orderBy('products.name');

        return ProductResource::collection($q->paginate($p['per_page'] ?? 50)->withQueryString())->additional(['filters' => [
            'categories' => Product::where('active', true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]]);
    }

    public function product(int $product)
    {
        return new ProductResource($this->productQuery()->findOrFail($product));
    }

    /** Active products with available = on hand - reserved at the caller's distributor location (read-only; no location is created). */
    private function productQuery()
    {
        $locId = Location::where('type', 'distributor')->where('distributor_id', $this->did())->value('id') ?? 0;

        return Product::where('products.active', true)
            ->leftJoin('stock_balances as sb', fn ($j) => $j->on('sb.product_id', '=', 'products.id')->where('sb.location_id', $locId))
            ->select('products.*')->selectRaw('COALESCE(sb.qty - sb.reserved, 0) as available_qty');
    }

    private function like(string $s): string
    {
        return '%'.addcslashes($s, '%_\\').'%';
    }
}
