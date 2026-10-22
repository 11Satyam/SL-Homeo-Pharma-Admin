<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\App\AttendanceResource;
use App\Http\Resources\App\OrderResource;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AttendanceService;
use App\Support\BusinessDay;

/** Today's figures for the signed-in staff member, all computed from live records. */
class DashboardController extends FieldController
{
    public function __construct(private AttendanceService $attendance) {}

    public function index()
    {
        $u = auth()->user();
        [$from, $to] = BusinessDay::range();
        $money = fn ($v) => number_format((float) $v, 2, '.', '');
        $mine = fn () => Order::where('distributor_id', $this->did())
            ->where(fn ($q) => $q->where('staff_id', $u->id)->orWhere('created_by', $u->id));

        $todayOrders = $mine()->whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled', 'failed']);
        $collections = Payment::where('collected_by', $u->id)->whereBetween('created_at', [$from, $to]);
        $expenses = Expense::where('user_id', $u->id)->whereDate('expense_date', BusinessDay::today());
        $att = $this->attendance->today($u);
        $status = $att?->status ?? 'not_checked_in';
        $needsDuty = config('fieldapp.attendance.require_check_in') && $status !== 'checked_in';

        return response()->json(['data' => [
            'date' => BusinessDay::today(),
            'staff' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role],
            'distributor' => ['id' => $u->distributor->id, 'name' => $u->distributor->business_name, 'area' => $u->distributor->area],
            'orders_today' => ['count' => (clone $todayOrders)->count(), 'value' => $money((clone $todayOrders)->sum('total'))],
            'collections_today' => [
                'count' => (clone $collections)->count(), 'amount' => $money((clone $collections)->sum('amount')),
                'pending_verification' => $money((clone $collections)->where('status', 'collected')->sum('amount')),
            ],
            'expenses_today' => [
                'submitted' => $money((clone $expenses)->where('status', 'submitted')->sum('amount')),
                'approved' => $money((clone $expenses)->where('status', 'approved')->sum('amount')),
            ],
            // Shop visits are not tracked as their own records; this counts shops with an order or collection today.
            'shop_visits' => ['tracked' => false, 'shops_served_today' => $mine()->whereBetween('created_at', [$from, $to])->pluck('shop_id')
                ->merge((clone $collections)->pluck('shop_id'))->unique()->count()],
            'attendance' => [
                'status' => $status, 'record' => $att ? new AttendanceResource($att) : null,
                'geofence' => $this->attendance->geofence($u->distributor),
            ],
            'deliveries' => $mine()->whereIn('status', ['confirmed', 'packed', 'out_for_delivery'])
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n),
            'quick_actions' => [
                'new_order' => ['enabled' => ! $needsDuty, 'reason' => $needsDuty ? 'check_in_required' : null],
                'collection' => ['enabled' => ! $needsDuty, 'reason' => $needsDuty ? 'check_in_required' : null],
                'expense' => ['enabled' => true, 'reason' => null],
                'check_in' => ['enabled' => $status === 'not_checked_in', 'reason' => $status === 'not_checked_in' ? null : 'already_checked_in'],
                'check_out' => ['enabled' => $status === 'checked_in', 'reason' => $status === 'checked_in' ? null : $status],
            ],
            'recent_orders' => OrderResource::collection($mine()->with('shop', 'staff', 'lines.product', 'invoice')->latest('id')->limit(5)->get()),
        ]]);
    }
}
