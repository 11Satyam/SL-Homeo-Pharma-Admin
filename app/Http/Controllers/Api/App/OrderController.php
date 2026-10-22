<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\OrderResource;
use App\Models\Order;
use App\Models\Shop;
use App\Services\AttendanceService;
use App\Services\SalesService;
use App\Support\Idempotency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Orders go through SalesService::createOrder, the same path as the Admin Panel:
 * prices from the product master, stock reserved on confirmation, deducted once at delivery.
 */
class OrderController extends FieldController
{
    public function __construct(private SalesService $sales, private AttendanceService $attendance) {}

    public function index(Request $r)
    {
        $p = $this->listParams($r, ['status' => 'nullable|string|max:30', 'shop_id' => 'nullable|integer', 'search' => 'nullable|string|max:50']);
        $q = $this->mine()->with('shop', 'staff', 'lines.product', 'invoice')
            ->when($p['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($p['shop_id'] ?? null, fn ($q, $s) => $q->where('shop_id', $s))
            ->when($p['search'] ?? null, fn ($q, $s) => $q->where('number', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->latest('id');

        return OrderResource::collection($this->applyDateRange($q, $p)->paginate($p['per_page'] ?? 20)->withQueryString());
    }

    public function show(int $order)
    {
        return new OrderResource($this->mine()->with('shop', 'staff', 'lines.product', 'invoice')->findOrFail($order));
    }

    public function store(Request $r)
    {
        $key = $this->idempotencyKey($r);
        $d = $r->validate([
            'shop_id' => ['required', 'integer', $this->ownShopRule()],
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('active', true)],
            'items.*.qty' => 'required|integer|min:1|max:100000',
            'remarks' => 'nullable|string|max:500',
        ], ['shop_id.exists' => 'Select an active shop of your distributor.', 'items.*.product_id.exists' => 'Product is not available for sale.']);
        $this->attendance->assertOnDuty($r->user());

        // Repeated product lines are merged; only product and quantity are taken from the client.
        $lines = [];
        foreach ($d['items'] as $i) {
            $lines[$i['product_id']] = ($lines[$i['product_id']] ?? 0) + (int) $i['qty'];
        }
        [$order, $replayed] = Idempotency::run(Order::class, 'created_by', $this->uid(), $key, fn () => $this->sales->createOrder(
            Shop::findOrFail($d['shop_id']), $lines, $this->uid(), ['remarks' => $d['remarks'] ?? null, 'idempotency_key' => $key]));

        return $this->created(new OrderResource($order->fresh(['shop', 'staff', 'lines.product', 'invoice'])), $replayed);
    }

    /** Same visibility as the Admin Panel gives staff: own distributor, assigned to or created by them. */
    private function mine()
    {
        return Order::where('distributor_id', $this->did())
            ->where(fn ($q) => $q->where('staff_id', $this->uid())->orWhere('created_by', $this->uid()));
    }
}
