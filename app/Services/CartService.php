<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use App\Support\Idempotency;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Field-app cart: one per staff member per shop. It holds only product + quantity; prices, GST and
 * availability are read live from the product master and the distributor's stock ledger, so totals are
 * never stale. Nothing is reserved until checkout, which goes through SalesService::createOrder — the
 * same path as POST /orders (server pricing, atomic reservation, idempotency).
 */
class CartService
{
    public const MAX_LINES = 100;

    public const MAX_QTY = 100000;

    public function __construct(private SalesService $sales) {}

    public function find(User $u, Shop $shop): ?Cart
    {
        return Cart::where('user_id', $u->id)->where('shop_id', $shop->id)->first();
    }

    /** Add a product; if it is already in the cart the quantities are combined (no duplicate line). */
    public function add(User $u, Shop $shop, int $productId, int $qty): Cart
    {
        return DB::transaction(function () use ($u, $shop, $productId, $qty) {
            $cart = $this->lockedCart($u, $shop);
            $product = $this->sellable($productId);
            $item = $cart->items()->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $item && $cart->items()->count() >= self::MAX_LINES) {
                throw ValidationException::withMessages(['product_id' => 'A cart can hold up to '.self::MAX_LINES.' different products. Check out this order first.']);
            }
            $newQty = ($item?->qty ?? 0) + $qty;
            $this->assertQty($shop, $product, $newQty, $item ? "You already have {$item->qty} in the cart. " : '');
            $item ? $item->update(['qty' => $newQty]) : $cart->items()->create(['product_id' => $product->id, 'qty' => $newQty]);
            $cart->touch();

            return $cart;
        });
    }

    /** Set an exact quantity; 0 removes the line. */
    public function setQty(User $u, Shop $shop, int $productId, int $qty): Cart
    {
        return DB::transaction(function () use ($u, $shop, $productId, $qty) {
            $cart = $this->find($u, $shop);
            $item = $cart?->items()->where('product_id', $productId)->lockForUpdate()->first();
            if (! $item) {
                throw ValidationException::withMessages(['product_id' => 'This product is not in the cart.']);
            }
            if ($qty === 0) {
                $item->delete();
            } else {
                $this->assertQty($shop, $this->sellable($productId), $qty);
                $item->update(['qty' => $qty]);
            }
            $cart->touch();

            return $cart;
        });
    }

    public function remove(User $u, Shop $shop, int $productId): ?Cart
    {
        $cart = $this->find($u, $shop);
        if (! $cart || ! $cart->items()->where('product_id', $productId)->delete()) {
            throw ValidationException::withMessages(['product_id' => 'This product is not in the cart.']);
        }
        $cart->touch();

        return $cart;
    }

    public function clear(User $u, Shop $shop): void
    {
        $this->find($u, $shop)?->delete(); // items cascade
    }

    /**
     * Turn the cart into a confirmed order. Re-validates everything; on success the cart is emptied in
     * the same transaction. A retried request with the same Idempotency-Key returns the original order.
     *
     * @return array{0: Order, 1: bool} [order, replayed]
     */
    public function checkout(User $u, Shop $shop, string $key, ?string $remarks): array
    {
        return Idempotency::run(Order::class, 'created_by', $u->id, $key, function () use ($u, $shop, $key, $remarks) {
            return DB::transaction(function () use ($u, $shop, $key, $remarks) {
                $cart = Cart::where('user_id', $u->id)->where('shop_id', $shop->id)->lockForUpdate()->first();
                $items = $cart?->items()->with('product')->get() ?? collect();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'The cart is empty. Add products before placing the order.']);
                }
                $inactive = $items->filter(fn ($i) => ! $i->product->active);
                if ($inactive->isNotEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'No longer for sale: '.$inactive->pluck('product.name')->implode(', ').'. Remove '.($inactive->count() > 1 ? 'them' : 'it').' and try again.']);
                }
                // createOrder reserves stock atomically and throws InsufficientStock listing every short line.
                $order = $this->sales->createOrder($shop, $items->pluck('qty', 'product_id')->all(), $u->id, ['remarks' => $remarks, 'idempotency_key' => $key]);
                $cart->delete();
                AuditLog::record('cart.checked_out', $order, ['cart_lines' => $items->count()]);

                return $order;
            });
        });
    }

    /** Priced, availability-checked view of a cart. Works for a missing/empty cart too. */
    public function summary(?Cart $cart, Shop $shop): array
    {
        $items = $cart ? $cart->items()->with('product')->orderBy('id')->get() : collect();
        $avail = $this->available($shop->distributor_id, $items->pluck('product_id')->all());
        $sub = $tax = $total = '0';
        $issues = [];
        $lines = $items->map(function (CartItem $i) use ($avail, &$sub, &$tax, &$total, &$issues) {
            $p = $i->product;
            $net = bcmul((string) $p->selling_price, (string) $i->qty, 2);
            $gross = $this->sales->lineTotal($p->selling_price, $p->tax_rate, $i->qty);
            $free = max(0, $avail[$p->id] ?? 0);
            $issue = ! $p->active ? 'inactive' : ($i->qty > $free ? 'insufficient_stock' : null);
            if ($issue) {
                $issues[] = ['product_id' => $p->id, 'code' => $issue, 'message' => $issue === 'inactive'
                    ? "{$p->name} is no longer for sale — remove it."
                    : "{$p->name}: {$i->qty} in cart but only {$free} available."];
            }
            $sub = bcadd($sub, $net, 2);
            $tax = bcadd($tax, bcsub($gross, $net, 2), 2);
            $total = bcadd($total, $gross, 2);

            return [
                'product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit, 'qty' => $i->qty,
                'price' => number_format((float) $p->selling_price, 2, '.', ''), 'tax_rate' => number_format((float) $p->tax_rate, 2, '.', ''),
                'subtotal' => $net, 'tax' => bcsub($gross, $net, 2), 'total' => $gross,
                'available_qty' => $free, 'issue' => $issue,
            ];
        })->values();

        return [
            'shop' => ['id' => $shop->id, 'name' => $shop->name],
            'items' => $lines,
            'line_count' => $lines->count(), 'unit_count' => (int) $lines->sum('qty'),
            'subtotal' => number_format((float) $sub, 2, '.', ''), 'discount_total' => '0.00',
            'tax_total' => number_format((float) $tax, 2, '.', ''), 'total' => number_format((float) $total, 2, '.', ''),
            'can_checkout' => $lines->isNotEmpty() && ! $issues, 'issues' => $issues,
            'updated_at' => $cart?->updated_at?->toIso8601String(),
        ];
    }

    /** Open carts for a staff member (one per shop). */
    public function openCarts(User $u)
    {
        return Cart::where('user_id', $u->id)->whereHas('items')->with('shop')->withCount('items')->latest('updated_at')->get();
    }

    private function lockedCart(User $u, Shop $shop): Cart
    {
        $find = fn () => Cart::where('user_id', $u->id)->where('shop_id', $shop->id)->lockForUpdate()->first();
        if ($cart = $find()) {
            return $cart;
        }
        try {
            return Cart::create(['user_id' => $u->id, 'shop_id' => $shop->id, 'distributor_id' => $shop->distributor_id]);
        } catch (UniqueConstraintViolationException) {
            return $find(); // created concurrently by another request
        }
    }

    private function sellable(int $productId): Product
    {
        $p = Product::find($productId);
        if (! $p || ! $p->active) {
            throw ValidationException::withMessages(['product_id' => 'This product is not available for sale.']);
        }

        return $p;
    }

    private function assertQty(Shop $shop, Product $p, int $qty, string $prefix = ''): void
    {
        if ($qty > self::MAX_QTY) {
            throw ValidationException::withMessages(['qty' => $prefix.'Quantity cannot exceed '.number_format(self::MAX_QTY).'.']);
        }
        $free = max(0, $this->available($shop->distributor_id, [$p->id])[$p->id] ?? 0);
        if ($qty > $free) {
            throw ValidationException::withMessages(['qty' => $prefix.($free > 0
                ? "Only {$free} {$p->unit} of {$p->name} available."
                : "{$p->name} is out of stock at this distributor.")]);
        }
    }

    /** @return array<int,int> product_id => on hand − reserved at the distributor's location (read-only). */
    private function available(int $distributorId, array $productIds): array
    {
        if (! $productIds) {
            return [];
        }
        $locId = Location::where('type', 'distributor')->where('distributor_id', $distributorId)->value('id');

        return $locId ? StockBalance::where('location_id', $locId)->whereIn('product_id', $productIds)
            ->selectRaw('product_id, qty - reserved as free')->pluck('free', 'product_id')->map(fn ($v) => (int) $v)->all() : [];
    }
}
