<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockBalance;
use App\Models\Transfer;
use App\Models\User;

/**
 * Sidebar structure and "needs attention" counts for the admin layout.
 * Counts are scoped exactly like the pages they link to.
 */
final class AdminNav
{
    public static function groups(): array
    {
        $field = ['admin', 'distributor', 'accounts_manager', 'viewer'];

        return [
            'Overview' => [['erp.dashboard', 'Dashboard', 'grid', []]],
            'Inventory' => [
                ['erp.products', 'Products', 'pill', []],
                ['erp.stock', 'Warehouse & Stock', 'warehouse', []],
                ['erp.transfers', 'Stock Transfers', 'truck', []],
                ['erp.ledger', 'Stock Ledger', 'ledger', []],
            ],
            'Sales' => [
                ['erp.orders', 'Orders & Deliveries', 'cart', []],
                ['erp.invoices', 'Invoices & Outstanding', 'file', []],
                ['erp.payments', 'Payments & Collections', 'wallet', []],
                ['erp.shops', 'Shops', 'store', []],
            ],
            'Field team' => [
                ['erp.attendance', 'Staff Attendance', 'pin', $field],
                ['erp.expenses', 'Staff Expenses', 'receipt', $field],
            ],
            'Administration' => [
                ['erp.distributors', 'Distributors', 'building', ['admin']],
                ['erp.users', 'Users & Staff', 'users', ['admin', 'distributor']],
                ['erp.audit', 'Audit Logs', 'shield', ['admin', 'viewer']],
            ],
        ];
    }

    /** @return array<string, array{count:int, hot:bool, title:string, text:string, icon:string, tone:string}> keyed by route */
    public static function alerts(User $u): array
    {
        $did = $u->scopedDistributorId();
        $scope = fn ($q) => $did ? $q->where('distributor_id', $did) : $q;
        $a = [];

        $openOrders = $scope(Order::whereIn('status', ['confirmed', 'packed', 'out_for_delivery']))->count();
        $a['erp.orders'] = ['count' => $openOrders, 'hot' => false, 'title' => $openOrders.' open orders', 'text' => 'Awaiting packing or delivery', 'icon' => 'cart', 'tone' => 'info'];

        $overdue = $scope(Invoice::query())->whereDate('due_date', '<', now()->toDateString())->whereRaw(
            'total > (select coalesce(sum(amount),0) from payment_allocations where invoice_id = invoices.id)'
            .' + (select coalesce(sum(amount),0) from credit_notes where invoice_id = invoices.id)')->count();
        $a['erp.invoices'] = ['count' => $overdue, 'hot' => $overdue > 0, 'title' => $overdue.' overdue invoices', 'text' => 'Past due date with balance outstanding', 'icon' => 'file', 'tone' => 'bad'];

        $unverified = $scope(Payment::where('status', 'collected'))->count();
        $a['erp.payments'] = ['count' => $unverified, 'hot' => false, 'title' => $unverified.' collections to verify', 'text' => 'Cash/UPI collected by staff, not yet reconciled', 'icon' => 'wallet', 'tone' => 'warn'];

        if ($u->hasRole('admin', 'distributor', 'accounts_manager', 'viewer')) {
            $exp = $scope(Expense::where('status', 'submitted'))->count();
            $a['erp.expenses'] = ['count' => $exp, 'hot' => false, 'title' => $exp.' expenses to review', 'text' => 'Submitted from the field app', 'icon' => 'receipt', 'tone' => 'warn'];
        }

        if (! $did) {
            $pending = Transfer::whereIn('status', ['draft', 'approved'])->count();
            $a['erp.transfers'] = ['count' => $pending, 'hot' => false, 'title' => $pending.' transfers pending', 'text' => 'Drafts to approve or approved to dispatch', 'icon' => 'truck', 'tone' => 'info'];
            $low = StockBalance::whereHas('location', fn ($q) => $q->where('type', 'warehouse'))
                ->join('products', 'products.id', '=', 'stock_balances.product_id')
                ->whereColumn('stock_balances.qty', '<=', 'products.reorder_level')->count();
            $a['erp.stock'] = ['count' => $low, 'hot' => $low > 0, 'title' => $low.' low-stock items', 'text' => 'Warehouse balance at or below reorder level', 'icon' => 'alert', 'tone' => 'bad'];
        } else {
            $inbound = Transfer::where('status', 'in_transit')->whereHas('to', fn ($l) => $l->where('distributor_id', $did))->count();
            $a['erp.transfers'] = ['count' => $inbound, 'hot' => false, 'title' => $inbound.' inbound transfers', 'text' => 'In transit to your location — receive on arrival', 'icon' => 'truck', 'tone' => 'info'];
        }

        return $a;
    }
}
