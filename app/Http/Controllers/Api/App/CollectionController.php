<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\CollectionResource;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\AttendanceService;
use App\Services\SalesService;
use App\Support\Idempotency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Collections are Payments in status "collected" (cash/UPI with the staff member).
 * They count as deposited only after accounts verify them in the Admin Panel.
 */
class CollectionController extends FieldController
{
    public function __construct(private SalesService $sales, private AttendanceService $attendance) {}

    public function index(Request $r)
    {
        $p = $this->listParams($r, ['status' => 'nullable|in:collected,verified', 'shop_id' => 'nullable|integer', 'distributor_id' => 'nullable|integer']);

        $q = Payment::with('shop', 'allocations')
            ->when(auth()->check(), fn ($q) => $q->where('distributor_id', $this->did())->where('collected_by', $this->uid()))
            ->when(! auth()->check() && ($p['distributor_id'] ?? null), fn ($q, $unused) => $q->where('distributor_id', $p['distributor_id']))
            ->when($p['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($p['shop_id'] ?? null, fn ($q, $s) => $q->where('shop_id', $s))
            ->latest('id');

        return CollectionResource::collection($this->applyDateRange($q, $p)->paginate($p['per_page'] ?? 20)->withQueryString());
    }

    public function store(Request $r)
    {
        $key = $this->idempotencyKey($r);
        $refRequired = config('fieldapp.collections.reference_required');
        $d = $r->validate([
            'shop_id' => ['required', 'integer', $this->ownShopRule()],
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:10000000',
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100', Rule::requiredIf(in_array($r->input('method'), $refRequired, true))],
            'remarks' => 'nullable|string|max:500',
            'collected_at' => 'nullable|date|before_or_equal:'.now()->addMinutes(5)->toIso8601String()
                .'|after_or_equal:'.now()->subDays(config('fieldapp.collections.max_backdate_days'))->toIso8601String(),
            'allocations' => 'nullable|array|max:50',
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct', Rule::exists('invoices', 'id')->where('shop_id', (int) $r->input('shop_id'))->where('distributor_id', $this->did())],
            'allocations.*.amount' => 'required|numeric|decimal:0,2|min:0.01',
        ], ['shop_id.exists' => 'Select an active shop of your distributor.', 'allocations.*.invoice_id.exists' => 'Invoice does not belong to this shop.']);
        $this->attendance->assertOnDuty($r->user());

        [$payment, $replayed] = Idempotency::run(Payment::class, 'collected_by', $this->uid(), $key, function () use ($d, $key) {
            $reference = isset($d['reference']) ? trim($d['reference']) : null;
            $this->rejectReusedReference($d['method'], $reference);
            $amount = number_format((float) $d['amount'], 2, '.', '');
            $alloc = collect($d['allocations'] ?? [])->mapWithKeys(fn ($a) => [$a['invoice_id'] => number_format((float) $a['amount'], 2, '.', '')])->all();

            return $this->sales->recordPayment(Shop::findOrFail($d['shop_id']), $amount, $d['method'], $reference ?: null, $alloc, [
                'remarks' => $d['remarks'] ?? null, 'idempotency_key' => $key,
                'collected_at' => isset($d['collected_at']) ? Carbon::parse($d['collected_at']) : now(),
            ]);
        });

        return $this->created(new CollectionResource($payment->fresh(['shop', 'allocations'])), $replayed);
    }

    /** A UPI/bank/cheque reference identifies one transfer; the same one twice for a distributor is a duplicate. */
    private function rejectReusedReference(string $method, ?string $reference): void
    {
        if ($method === 'cash' || ! $reference) {
            return;
        }
        $dup = Payment::where('distributor_id', $this->did())->where('method', $method)
            ->whereRaw('LOWER(reference) = ?', [mb_strtolower($reference)])->exists();
        if ($dup) {
            throw ValidationException::withMessages(['reference' => 'This reference number has already been recorded.']);
        }
    }
}
