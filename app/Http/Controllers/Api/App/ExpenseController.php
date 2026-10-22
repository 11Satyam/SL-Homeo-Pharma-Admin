<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\BusinessDay;
use App\Support\Idempotency;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff submit expenses; review happens in the Admin Panel. There is no edit endpoint, so reviewed expenses cannot change. */
class ExpenseController extends FieldController
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $r)
    {
        $p = $this->listParams($r, ['status' => 'nullable|in:submitted,approved,rejected']);
        $q = Expense::where('user_id', $this->uid())->where('distributor_id', $this->did())
            ->when($p['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($p['from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($p['to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->latest('id');

        return ExpenseResource::collection($q->paginate($p['per_page'] ?? 20)->withQueryString());
    }

    /** JSON or multipart/form-data (multipart when attaching a receipt). */
    public function store(Request $r)
    {
        $key = $this->idempotencyKey($r);
        $today = BusinessDay::today();
        $cfg = config('fieldapp.expenses');
        $d = $r->validate([
            'category' => ['required', Rule::in($cfg['categories'])],
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:'.$cfg['max_amount'],
            'expense_date' => 'required|date_format:Y-m-d|before_or_equal:'.$today
                .'|after_or_equal:'.CarbonImmutable::parse($today)->subDays($cfg['max_age_days'])->toDateString(),
            'description' => 'nullable|required_if:category,other|string|max:500',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:'.$cfg['receipt_max_kb'],
        ]);

        [$expense, $replayed] = Idempotency::run(Expense::class, 'user_id', $this->uid(), $key,
            fn () => $this->expenses->submit($r->user(), $d + ['idempotency_key' => $key], $r->file('receipt')));

        return $this->created(new ExpenseResource($expense), $replayed);
    }
}
