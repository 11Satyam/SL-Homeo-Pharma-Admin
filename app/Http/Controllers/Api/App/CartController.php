<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\OrderResource;
use App\Models\Shop;
use App\Services\AttendanceService;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Field-app cart: build an order for a shop line by line, then check out into a normal confirmed order. */
class CartController extends FieldController
{
    public function __construct(private CartService $carts, private AttendanceService $attendance) {}

    /** GET /cart?shop_id= */
    public function show(Request $r)
    {
        $shop = $this->shop($r, false);

        return response()->json(['data' => $this->carts->summary($this->carts->find($r->user(), $shop), $shop)]);
    }

    /** GET /carts — every shop with an open cart. */
    public function index(Request $r)
    {
        return response()->json(['data' => $this->carts->openCarts($r->user())->map(fn ($c) => [
            'shop' => ['id' => $c->shop->id, 'name' => $c->shop->name], 'line_count' => $c->items_count,
            'updated_at' => $c->updated_at->toIso8601String(),
        ])->values()]);
    }

    /** POST /cart/items { shop_id, product_id, qty } — adds to an existing line if the product is already there. */
    public function add(Request $r)
    {
        $shop = $this->shop($r, true);
        $d = $r->validate(['product_id' => 'required|integer', 'qty' => 'required|integer|min:1|max:'.CartService::MAX_QTY]);
        $cart = $this->carts->add($r->user(), $shop, (int) $d['product_id'], (int) $d['qty']);

        return response()->json(['data' => $this->carts->summary($cart->fresh(), $shop)]);
    }

    /** PATCH /cart/items/{product} { shop_id, qty } — sets the quantity; 0 removes the line. */
    public function update(Request $r, int $product)
    {
        $shop = $this->shop($r, true);
        $qty = (int) $r->validate(['qty' => 'required|integer|min:0|max:'.CartService::MAX_QTY])['qty'];
        $cart = $this->carts->setQty($r->user(), $shop, $product, $qty);

        return response()->json(['data' => $this->carts->summary($cart->fresh(), $shop)]);
    }

    /** DELETE /cart/items/{product}?shop_id= */
    public function remove(Request $r, int $product)
    {
        $shop = $this->shop($r, false);
        $cart = $this->carts->remove($r->user(), $shop, $product);

        return response()->json(['data' => $this->carts->summary($cart?->fresh(), $shop)]);
    }

    /** DELETE /cart?shop_id= */
    public function clear(Request $r)
    {
        $shop = $this->shop($r, false);
        $this->carts->clear($r->user(), $shop);

        return response()->json(['data' => $this->carts->summary(null, $shop), 'message' => 'Cart cleared.']);
    }

    /** POST /cart/checkout { shop_id, remarks? } + Idempotency-Key — places the order and empties the cart. */
    public function checkout(Request $r)
    {
        $key = $this->idempotencyKey($r);
        $shop = $this->shop($r, true);
        $remarks = $r->validate(['remarks' => 'nullable|string|max:500'])['remarks'] ?? null;
        $this->attendance->assertOnDuty($r->user());
        [$order, $replayed] = $this->carts->checkout($r->user(), $shop, $key, $remarks);

        return $this->created(new OrderResource($order->fresh(['shop', 'staff', 'lines.product', 'invoice'])), $replayed);
    }

    /** The shop must belong to the caller's distributor; adding/checking out also needs it to be active. */
    private function shop(Request $r, bool $mustBeActive): Shop
    {
        $rule = Rule::exists('shops', 'id')->where('distributor_id', $this->did());
        if ($mustBeActive) {
            $rule = $rule->where('status', 'active');
        }
        $id = $r->validate(['shop_id' => ['required', 'integer', $rule]], ['shop_id.exists' => $mustBeActive
            ? 'Select an active shop of your distributor.' : 'Select a shop of your distributor.'])['shop_id'];

        return Shop::findOrFail($id);
    }
}
