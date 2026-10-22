<?php

namespace App\Support;

use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;

/**
 * "What should I do next?" for the signed-in user: role-relevant pending work,
 * quick actions and the first-time setup checklist. Scoped like the pages they link to.
 */
final class Guide
{
    private const OPEN_ORDER = ['confirmed', 'packed', 'out_for_delivery'];

    /** @return list<array{n:int, title:string, why:string, url:string, button:string}> most urgent first */
    public static function tasks(User $u): array
    {
        $did = $u->scopedDistributorId();
        $scope = fn ($q) => $did ? $q->where('distributor_id', $did) : $q;
        $tasks = [];
        $add = function (bool $role, \Closure $count, string $title, string $why, string $url, string $button) use (&$tasks) {
            if ($role && ($n = $count()) > 0) {
                $tasks[] = compact('n', 'title', 'why', 'url', 'button');
            }
        };

        $add($u->hasRole('admin'), fn () => Transfer::where('status', 'draft')->count(), 'stock transfer(s) waiting for your approval',
            'Stock will not leave the warehouse until a transfer is approved.', route('erp.transfers', ['status' => 'draft']), 'Review & approve');
        $add($u->hasRole('admin', 'inventory_manager'), fn () => Transfer::where('status', 'approved')->count(), 'approved transfer(s) to dispatch',
            'Load the goods, then click Dispatch so the stock shows as in transit.', route('erp.transfers', ['status' => 'approved']), 'Dispatch');
        $add($u->hasRole('admin', 'distributor', 'inventory_manager'), fn () => Transfer::whereIn('status', ['in_transit', 'partially_received'])
            ->when($did, fn ($q) => $q->whereHas('to', fn ($l) => $l->where('distributor_id', $did)))->count(),
            $did ? 'stock delivery(ies) on the way to you' : 'stock delivery(ies) on the way to distributors',
            'When the goods arrive, count them and click Receive. Stock can be sold only after that.', route('erp.transfers', ['status' => 'in_transit']), 'Receive stock');
        $add($u->hasRole('admin', 'distributor', 'staff'), fn () => $scope(Order::whereIn('status', self::OPEN_ORDER))
            ->when($u->hasRole('staff'), fn ($q) => $q->where('staff_id', $u->id))->count(),
            $u->hasRole('staff') ? 'order(s) assigned to you to deliver' : 'open order(s) to deliver',
            'Deliver the goods and record the delivery; that creates the invoice.', route('erp.orders', ['status' => 'open']), 'Go to deliveries');
        $add($u->hasRole('admin', 'distributor', 'accounts_manager'), fn () => $scope(Payment::where('status', 'collected'))->count(), 'payment(s) to verify',
            'Check the cash box or bank account, then click Verify.', route('erp.payments', ['status' => 'collected']), 'Verify payments');
        $add($u->hasRole('admin', 'distributor', 'accounts_manager'), fn () => $scope(Expense::where('status', 'submitted'))->where('user_id', '!=', $u->id)->count(),
            'staff expense(s) to review', 'Approve or reject each claim from the mobile app.', route('erp.expenses', ['status' => 'submitted']), 'Review expenses');
        $add($u->hasRole('admin', 'distributor', 'staff', 'accounts_manager'), fn () => $scope(Invoice::query())->overdue()->count(), 'overdue invoice(s)',
            'These shops are past their payment date. Follow up and record the payment.', route('erp.invoices', ['status' => 'overdue']), 'See who owes');
        $add($u->hasRole('admin', 'inventory_manager', 'distributor'), fn () => self::lowStock($u)->count(), 'product(s) low on stock',
            $did ? 'Ask the company to send more stock.' : 'Receive more stock or reorder from your supplier.', route('erp.stock'), 'See stock');

        return $tasks;
    }

    /** Warehouse balances (or the distributor's own) at or below the product's low-stock level. */
    public static function lowStock(User $u)
    {
        $did = $u->scopedDistributorId();

        return StockBalance::with('product', 'location')
            ->whereHas('location', fn ($q) => $did ? $q->where('type', 'distributor')->where('distributor_id', $did) : $q->where('type', 'warehouse'))
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.qty', '<=', 'products.reorder_level')->select('stock_balances.*')->get();
    }

    /** @return list<array{0:string, 1:string, 2:bool}> [label, url, primary] */
    public static function actions(User $u): array
    {
        return array_values(array_filter([
            $u->hasRole('admin', 'distributor', 'staff') ? ['New order', route('erp.orders', ['new' => 1]), true] : null,
            $u->hasRole('admin', 'distributor', 'staff') ? ['Record a payment', route('erp.payments', ['new' => 1]), false] : null,
            $u->hasRole('admin', 'distributor') ? ['Add a shop', route('erp.shops', ['new' => 1]), false] : null,
            $u->hasRole('admin', 'distributor') ? ['Add staff', route('erp.users', ['new' => 1]), false] : null,
            $u->hasRole('admin', 'inventory_manager') ? ['Receive stock', route('erp.stock', ['new' => 1]), $u->hasRole('inventory_manager')] : null,
            $u->hasRole('admin', 'inventory_manager') ? ['Send stock to a distributor', route('erp.transfers', ['new' => 1]), false] : null,
            $u->hasRole('admin') ? ['Register a distributor', route('erp.distributors', ['new' => 1]), false] : null,
            $u->hasRole('accounts_manager') ? ['Verify payments', route('erp.payments', ['status' => 'collected']), true] : null,
            ['Help & examples', route('erp.help'), false],
        ]));
    }

    /** First-time setup progress for admins and distributor owners. @return list<array{0:string, 1:bool, 2:string}> [label, done, url] */
    public static function checklist(User $u): array
    {
        if ($u->hasRole('admin')) {
            return [
                ['Create a warehouse', Location::where('type', 'warehouse')->exists(), route('erp.stock', ['new' => 1])],
                ['Add your first product', Product::exists(), route('erp.products', ['new' => 1])],
                ['Receive stock into the warehouse', StockMovement::whereIn('type', ['opening', 'receipt'])->exists(), route('erp.stock', ['new' => 1])],
                ['Register a distributor', Distributor::exists(), route('erp.distributors', ['new' => 1])],
                ['Send stock to a distributor', Transfer::exists(), route('erp.transfers', ['new' => 1])],
                ['Distributor receives the stock', Transfer::whereIn('status', ['received', 'partially_received'])->exists(), route('erp.transfers', ['status' => 'in_transit'])],
                ['Add a shop', Shop::exists(), route('erp.shops', ['new' => 1])],
                ['Add field staff', User::where('role', 'staff')->exists(), route('erp.users', ['new' => 1])],
                ['Deliver a first order (creates an invoice)', Invoice::exists(), route('erp.orders', ['new' => 1])],
                ['Record and verify a payment', Payment::where('status', 'verified')->exists(), route('erp.payments', ['new' => 1])],
            ];
        }
        if ($u->hasRole('distributor') && $u->distributor_id) {
            $did = $u->distributor_id;
            $loc = Location::where('type', 'distributor')->where('distributor_id', $did)->value('id');

            return [
                ['Receive your first stock', Transfer::where('to_location_id', $loc)->whereIn('status', ['received', 'partially_received'])->exists(), route('erp.transfers', ['status' => 'in_transit'])],
                ['Add your field staff', User::where('distributor_id', $did)->where('role', 'staff')->exists(), route('erp.users', ['new' => 1])],
                ['Add your shops', Shop::where('distributor_id', $did)->exists(), route('erp.shops', ['new' => 1])],
                ['Take a first order', Order::where('distributor_id', $did)->exists(), route('erp.orders', ['new' => 1])],
                ['Record a delivery (creates the invoice)', Invoice::where('distributor_id', $did)->exists(), route('erp.orders', ['status' => 'open'])],
                ['Record a payment from a shop', Payment::where('distributor_id', $did)->exists(), route('erp.payments', ['new' => 1])],
                ['Verify a payment', Payment::where('distributor_id', $did)->where('status', 'verified')->exists(), route('erp.payments', ['status' => 'collected'])],
            ];
        }

        return [];
    }
}
