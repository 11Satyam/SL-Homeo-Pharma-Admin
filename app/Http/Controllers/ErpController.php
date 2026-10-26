<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\ExpenseService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Help;
use App\Support\Ui;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin/distributor/staff screens. Every query for distributor-owned data goes
 * through scoped() so distributor and staff users only see their own records.
 */
class ErpController extends Controller
{
    public function __construct(private StockService $stock, private TransferService $transfers, private SalesService $sales) {}

    private function scoped($query, string $col = 'distributor_id')
    {
        $id = auth()->user()->scopedDistributorId();

        return $id ? $query->where($col, $id) : $query;
    }

    private function scopedTransfers()
    {
        $id = auth()->user()->scopedDistributorId();

        return Transfer::query()->when($id, fn ($q) => $q->where(fn ($q) => $q
            ->whereHas('to', fn ($l) => $l->where('distributor_id', $id))
            ->orWhereHas('from', fn ($l) => $l->where('distributor_id', $id))));
    }

    private function own(?int $distributorId): void
    {
        $id = auth()->user()->scopedDistributorId();
        abort_if($id && $id !== $distributorId, 403);
    }

    /** Success message plus "what to do next" links (session 'next' => [[label, url], ...]) rendered by the layout. */
    private function done(string $message, array $next = [])
    {
        return back()->with('status', $message)->with('next', $next);
    }

    /** A business-rule problem shown next to the field, keeping what the user typed. */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private static function anyQty(array $lines): bool
    {
        return (bool) array_filter($lines, fn ($q) => (int) $q > 0);
    }

    // ---------- Help ----------
    public function help()
    {
        return view('erp.help', ['guide' => Help::guide(auth()->user()), 'faq' => Help::faq(auth()->user())]);
    }

    // ---------- Dashboard ----------
    /** Read-only overview. Every figure is scoped like the module it links to. */
    public function dashboard()
    {
        $did = auth()->user()->scopedDistributorId();
        $bal = fn ($types) => (int) StockBalance::whereHas('location', fn ($q) => $q->whereIn('type', (array) $types)
            ->when($did, fn ($q) => $q->where('distributor_id', $did)))->sum('qty');

        // Receivables: outstanding = total - allocations - credit notes (same rule as Invoice::outstanding()).
        $inv = $this->scoped(Invoice::with('shop', 'allocations', 'creditNotes'))->get()->each(function ($i) {
            $i->due = (float) $i->total - (float) $i->allocations->sum('amount') - (float) $i->creditNotes->sum('amount');
        });
        $open = $inv->filter(fn ($i) => $i->due > 0.004);
        $overdue = $open->filter(fn ($i) => $i->due_date->lt(today()));
        $aging = ['Not due' => 0, '1–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, '90+ days' => 0];
        foreach ($open as $i) {
            $late = (int) $i->due_date->diffInDays(today(), false);
            $aging[$late <= 0 ? 'Not due' : ($late <= 30 ? '1–30 days' : ($late <= 60 ? '31–60 days' : ($late <= 90 ? '61–90 days' : '90+ days')))] += $i->due;
        }

        // Sales trend: invoiced value per day, last 14 days (invoices are issued at delivery).
        $from = today()->subDays(13);
        $byDay = $this->scoped(Invoice::whereDate('issue_date', '>=', $from))->get(['issue_date', 'total'])
            ->groupBy(fn ($i) => $i->issue_date->toDateString())->map(fn ($g) => (float) $g->sum('total'));
        $trend = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $trend[$d->format('d M')] = $byDay[$d->toDateString()] ?? 0;
        }
        $sales30 = (float) $this->scoped(Invoice::whereDate('issue_date', '>', today()->subDays(30)))->sum('total');
        $salesPrev30 = (float) $this->scoped(Invoice::whereDate('issue_date', '>', today()->subDays(60))->whereDate('issue_date', '<=', today()->subDays(30)))->sum('total');

        $statusCounts = $this->scoped(Order::where('created_at', '>=', today()->subDays(30)))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $pipeline = collect(['confirmed', 'packed', 'out_for_delivery', 'delivered', 'partially_delivered', 'failed', 'cancelled'])
            ->mapWithKeys(fn ($s) => [$s => (int) ($statusCounts[$s] ?? 0)]);

        $top = OrderLine::query()->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->join('products', 'products.id', '=', 'order_lines.product_id')
            ->when($did, fn ($q) => $q->where('orders.distributor_id', $did))
            ->where('orders.delivered_at', '>=', today()->subDays(30))->where('order_lines.delivered_qty', '>', 0)
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name, sum(order_lines.delivered_qty) as units, sum(order_lines.delivered_qty * order_lines.price * (1 + order_lines.tax_rate / 100)) as value')
            ->orderByDesc('value')->limit(6)->get();

        $collections = $this->scoped(Payment::where('created_at', '>=', today()->subDays(30)))->get(['amount', 'status']);

        return view('erp.dashboard', [
            'did' => $did,
            'stats' => [
                'sales30' => $sales30, 'salesPrev30' => $salesPrev30,
                'salesToday' => (float) $this->scoped(Invoice::whereDate('issue_date', today()))->sum('total'),
                'ordersToday' => $this->scoped(Order::whereDate('created_at', today()))->count(),
                'openOrders' => $this->scoped(Order::whereIn('status', ['confirmed', 'packed', 'out_for_delivery']))->count(),
                'outstanding' => $open->sum('due'), 'overdueAmount' => $overdue->sum('due'), 'overdueCount' => $overdue->count(),
                'collected30' => (float) $collections->sum('amount'),
                'unverifiedAmount' => (float) $collections->where('status', 'collected')->sum('amount'),
                'unverifiedCount' => $this->scoped(Payment::where('status', 'collected'))->count(),
                'expensesToReview' => $this->scoped(Expense::where('status', 'submitted'))->count(),
                'expensesToReviewAmount' => (float) $this->scoped(Expense::where('status', 'submitted'))->sum('amount'),
                'checkedIn' => $this->scoped(Attendance::whereDate('date', \App\Support\BusinessDay::today()))->count(),
                'fieldStaff' => $this->scoped(User::where('role', 'staff')->where('status', 'active'))->count(),
                'products' => Product::where('active', true)->count(),
                'shops' => $this->scoped(Shop::where('status', 'active'))->count(),
                'distributors' => Distributor::where('status', 'active')->count(),
                'pendingTransfers' => $did ? $this->scopedTransfers()->where('status', 'in_transit')->count() : Transfer::whereIn('status', ['draft', 'approved'])->count(),
            ],
            'stock' => ['Warehouse' => $did ? 0 : $bal('warehouse'), 'Distributor' => $bal('distributor'), 'In transit' => $did ? 0 : $bal('transit')],
            'damaged' => $did ? null : $bal('damaged'),
            'trend' => $trend, 'aging' => $aging, 'pipeline' => $pipeline, 'topProducts' => $top,
            'overdueList' => $overdue->sortByDesc('due')->take(5)->values(),
            'lowStock' => $did ? collect() : StockBalance::with('product', 'location')
                ->whereHas('location', fn ($q) => $q->where('type', 'warehouse'))
                ->join('products', 'products.id', '=', 'stock_balances.product_id')
                ->whereColumn('stock_balances.qty', '<=', 'products.reorder_level')->select('stock_balances.*')->get(),
            'activity' => $this->activity($did),
        ]);
    }

    /** Recent business events across modules, newest first. */
    private function activity(?int $did)
    {
        $s = fn ($q) => $did ? $q->where('distributor_id', $did) : $q;
        $rows = collect();
        foreach ($s(Order::with('shop', 'staff'))->latest('id')->limit(6)->get() as $o) {
            $rows->push(['at' => $o->delivered_at ?? $o->created_at, 'icon' => $o->delivered_at ? 'package-check' : 'cart', 'tone' => $o->delivered_at ? 'ok' : 'info',
                'text' => ($o->delivered_at ? 'Delivered ' : 'New order ').$o->number, 'sub' => $o->shop->name.' · '.\App\Support\Fmt::money($o->total), 'href' => route('erp.orders')]);
        }
        foreach ($s(Payment::with('shop', 'collector'))->latest('id')->limit(5)->get() as $p) {
            $rows->push(['at' => $p->created_at, 'icon' => 'wallet', 'tone' => $p->status === 'verified' ? 'ok' : 'warn',
                'text' => \App\Support\Fmt::money($p->amount).' collected via '.\App\Support\Fmt::label($p->method), 'sub' => $p->shop->name.($p->collector ? ' · by '.$p->collector->name : ''), 'href' => route('erp.payments')]);
        }
        foreach ($s(Expense::with('user'))->latest('id')->limit(3)->get() as $e) {
            $rows->push(['at' => $e->created_at, 'icon' => 'receipt', 'tone' => 'violet',
                'text' => \App\Support\Fmt::money($e->amount).' '.$e->category.' expense', 'sub' => $e->user->name.' · '.\App\Support\Fmt::status($e->status)[1], 'href' => route('erp.expenses')]);
        }
        if (! $did) {
            foreach (Transfer::with('from', 'to')->latest('id')->limit(4)->get() as $t) {
                $rows->push(['at' => $t->received_at ?? $t->dispatched_at ?? $t->created_at, 'icon' => 'truck', 'tone' => 'info',
                    'text' => 'Transfer '.$t->number.' '.strtolower(\App\Support\Fmt::status($t->status)[1]), 'sub' => $t->from->name.' → '.$t->to->name, 'href' => route('erp.transfers')]);
            }
        }

        return $rows->sortByDesc('at')->take(9)->values();
    }

    // ---------- Products ----------
    public function products(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive']);
        $q = Product::query()
            ->withSum(['balances as on_hand' => fn ($b) => $b->whereHas('location', fn ($l) => $this->scoped($l->whereIn('type', ['warehouse', 'distributor', 'transit'])))], 'qty')
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('active', $s === 'active'))
            ->orderBy('name');

        return view('erp.products', ['products' => $q->paginate(25)->withQueryString(),
            'categories' => Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'counts' => ['all' => Product::count(), 'active' => Product::where('active', true)->count(), 'inactive' => Product::where('active', false)->count()]]);
    }

    public function saveProduct(Request $r, ?Product $product = null)
    {
        $data = $r->validate([
            'name' => 'required|max:255', 'sku' => ['required', 'max:64', Rule::unique('products')->ignore($product?->id)],
            'category' => 'nullable|max:100', 'unit' => 'required|max:20', 'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0', 'tax_rate' => 'required|numeric|between:0,100',
            'reorder_level' => 'required|integer|min:0', 'active' => 'boolean',
        ], ['sku.unique' => 'Another product already uses this code (SKU). Each product needs its own code.']);
        $product = $product ?? new Product;
        $before = $product->getOriginal();
        $product->fill($data + ['active' => $r->boolean('active')])->save();
        AuditLog::record($before ? 'product.updated' : 'product.created', $product, ['before' => $before]);

        return $before
            ? $this->done("Product \"{$product->name}\" updated. New orders use the new price; existing invoices keep theirs.")
            : $this->done("Product \"{$product->name}\" added.", [['Receive stock for it', route('erp.stock', ['new' => 1, 'product' => $product->id])], ['Add another product', route('erp.products', ['new' => 1])]]);
    }

    // ---------- Stock / warehouses / ledger ----------
    public function stock(Request $r)
    {
        $balances = StockBalance::with('product', 'location')
            ->whereHas('location', fn ($q) => $this->scoped($q->whereIn('type', ['warehouse', 'distributor', 'transit'])))
            ->where('qty', '!=', 0)->orderBy('location_id')->get();

        return view('erp.stock', [
            'balances' => $balances, 'warehouses' => Location::where('type', 'warehouse')->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveWarehouse(Request $r)
    {
        $name = trim($r->validate(['name' => 'required|max:100'])['name']);
        if (Location::where('type', 'warehouse')->whereRaw('LOWER(name) = ?', [Str::lower($name)])->exists()) {
            $this->fail('name', "A warehouse called \"{$name}\" already exists.");
        }
        $loc = Location::create(['name' => $name, 'type' => 'warehouse']);
        AuditLog::record('warehouse.created', $loc);

        return $this->done("Warehouse \"{$name}\" created.", [['Receive stock into it', route('erp.stock', ['new' => 1, 'location' => $loc->id])]]);
    }

    /** Opening stock / verified receipt: external -> warehouse, idempotent per form token. */
    public function receiveStock(Request $r)
    {
        $d = $r->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('type', 'warehouse')],
            'product_id' => 'required|exists:products,id', 'qty' => 'required|integer|min:1',
            'type' => 'required|in:opening,receipt', 'reason' => 'nullable|max:255', 'key' => 'required|string|max:64',
        ], ['location_id.required' => 'Choose a warehouse. If there is none yet, create one first.', 'qty.min' => 'Enter how many units arrived (at least 1).']);
        $loc = Location::findOrFail($d['location_id']);
        $m = $this->stock->move($d['product_id'], Location::system('external'), $loc,
            $d['qty'], $d['type'], 'rcv-'.$d['key'], null, $d['reason'] ?? null);
        $p = Product::find($d['product_id']);

        return $this->done($m->wasRecentlyCreated
            ? "{$d['qty']} {$p->unit} of {$p->name} added to {$loc->name}. Available there now: ".$this->stock->available($loc, $p->id).'.'
            : 'This stock entry was already saved, so it was not added twice.',
            [['Send stock to a distributor', route('erp.transfers', ['new' => 1, 'from' => $loc->id])], ['Receive another product', route('erp.stock', ['new' => 1, 'location' => $loc->id])]]);
    }

    /** Write-off damaged stock from a warehouse (adjustment entry, never a balance edit). */
    public function adjustStock(Request $r)
    {
        $d = $r->validate([
            'location_id' => 'required|exists:locations,id', 'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1', 'reason' => 'required|max:255', 'key' => 'required|string|max:64',
        ], ['reason.required' => 'Write why the stock is being written off, for example "Expired" or "Broken in storage".']);
        $loc = Location::findOrFail($d['location_id']);
        $this->own($loc->distributor_id);
        $available = $this->stock->available($loc, (int) $d['product_id']);
        if ($d['qty'] > $available) {
            $this->fail('qty', "Only {$available} unit(s) are available at {$loc->name} to write off (stock reserved for orders cannot be written off).");
        }
        $this->stock->move($d['product_id'], $loc, Location::system('damaged'), $d['qty'], 'damage_adjustment', 'adj-'.$d['key'], null, $d['reason']);

        return $this->done("{$d['qty']} unit(s) written off from {$loc->name} as damaged. The entry is in the Stock Ledger.");
    }

    public function ledger(Request $r)
    {
        $did = auth()->user()->scopedDistributorId();
        $q = StockMovement::with('product', 'from', 'to', 'user')->latest('id')
            ->when($r->product_id, fn ($q, $v) => $q->where('product_id', $v))
            ->when($r->type, fn ($q, $v) => $q->where('type', $v))
            ->when($did, fn ($q) => $q->where(fn ($q) => $q->whereHas('from', fn ($l) => $l->where('distributor_id', $did))
                ->orWhereHas('to', fn ($l) => $l->where('distributor_id', $did))));

        if ($r->export) {
            return response()->streamDownload(function () use ($q) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['id', 'date', 'type', 'product', 'qty', 'from', 'to', 'reference', 'reason', 'user']);
                foreach ($q->cursor() as $m) {
                    fputcsv($out, [$m->id, $m->created_at, $m->type, $m->product->sku, $m->qty, $m->from->name, $m->to->name, $m->reference, $m->reason, $m->user?->name]);
                }
            }, 'stock-ledger.csv');
        }

        return view('erp.ledger', ['movements' => $q->paginate(50)->withQueryString(), 'products' => Product::orderBy('name')->get(),
            'mismatches' => $did ? [] : $this->stock->reconcile()]);
    }

    public function reverseMovement(Request $r, StockMovement $movement)
    {
        $reason = $r->validate(['reason' => 'required|max:255'], ['reason.required' => 'Write why this entry is being undone.'])['reason'];
        if ($movement->type === 'reversal' || StockMovement::where('reverses_id', $movement->id)->exists()) {
            $this->fail('reason', "Entry #{$movement->id} has already been undone (or is itself an undo).");
        }
        $this->stock->reverse($movement, $reason);

        return $this->done("Entry #{$movement->id} undone with an equal and opposite entry. You can now enter the correct figure.");
    }

    // ---------- Distributors & users ----------
    public function distributors()
    {
        return view('erp.distributors', ['distributors' => Distributor::withCount('shops')->with('users')->orderBy('business_name')->get()]);
    }

    public function saveDistributor(Request $r)
    {
        $d = $r->validate([
            'business_name' => 'required|max:255', 'contact_person' => 'nullable|max:255', 'phone' => 'nullable|max:30',
            'email' => 'required|email|unique:users,email', 'area' => 'nullable|max:100', 'address' => 'nullable|max:500',
            'credit_limit' => 'nullable|numeric|min:0',
        ], ['email.unique' => 'Someone already signs in with this email. Use a different email for the distributor owner.']);
        if (Distributor::whereRaw('LOWER(business_name) = ?', [Str::lower(trim($d['business_name']))])->exists()) {
            $this->fail('business_name', "A distributor called \"{$d['business_name']}\" is already registered.");
        }
        $loc = DB::transaction(function () use ($d) {
            $dist = Distributor::create(array_filter($d, fn ($v) => $v !== null));
            $loc = Location::create(['name' => $dist->business_name, 'type' => 'distributor', 'distributor_id' => $dist->id]);
            $this->makeUser($dist->contact_person ?: $dist->business_name, $d['email'], 'distributor', $dist->id);
            AuditLog::record('distributor.created', $dist);

            return $loc;
        });

        return $this->done("Distributor \"{$d['business_name']}\" registered. An email was sent to {$d['email']} so the owner can set a password and sign in.",
            [['Send them stock now', route('erp.transfers', ['new' => 1, 'to' => $loc->id])], ['Add a shop for them', route('erp.shops', ['new' => 1])]]);
    }

    public function setDistributorStatus(Request $r, Distributor $distributor)
    {
        $status = $r->validate(['status' => 'required|in:active,inactive'])['status'];
        $distributor->update(['status' => $status]);
        // Deactivating a distributor locks its users out; history is untouched.
        $distributor->users()->update(['status' => $status]);
        AuditLog::record('distributor.'.$status, $distributor);

        return $this->done($status === 'active'
            ? "{$distributor->business_name} is active again; its users can sign in."
            : "{$distributor->business_name} deactivated. Its users can no longer sign in; all records are kept.");
    }

    public function users()
    {
        return view('erp.users', ['users' => $this->scoped(User::with('distributor'))->orderBy('role')->get(),
            'distributors' => Distributor::where('status', 'active')->get()]);
    }

    public function saveUser(Request $r)
    {
        $me = $r->user();
        $roles = $me->hasRole('admin') ? User::ROLES : ['staff'];
        $d = $r->validate([
            'name' => 'required|max:255', 'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in($roles)], 'distributor_id' => 'nullable|exists:distributors,id',
            'login' => 'nullable|in:invite,password',
            'password' => 'required_if:login,password|nullable|string|min:8|max:255|confirmed',
        ], ['email.unique' => 'Someone already signs in with this email address.',
            'password.required_if' => 'Enter a password, or choose “Email an activation link”.']);
        $did = $me->scopedDistributorId() ?? $d['distributor_id'] ?? null;
        if (in_array($d['role'], Ui::DISTRIBUTOR_ROLES, true) && ! $did) {
            $this->fail('distributor_id', 'Choose which distributor this '.Ui::label($d['role']).' works for.');
        }
        $phone = null;
        if (filled($d['phone'] ?? null)) {
            $phone = User::normalizePhone($d['phone']) ?? $this->fail('phone', 'Enter a valid 10-digit mobile number.');
            if (User::where('phone', $phone)->exists()) {
                $this->fail('phone', 'Someone already signs in with this mobile number.');
            }
        }
        $setNow = ($d['login'] ?? 'invite') === 'password';
        $u = $this->makeUser($d['name'], $d['email'], $d['role'], in_array($d['role'], Ui::DISTRIBUTOR_ROLES, true) ? $did : null, $setNow ? $d['password'] : null);
        if ($phone) {
            $u->update(['phone' => $phone]);
        }

        return $this->done("{$d['name']} added as ".Ui::label($d['role']).'. '.($setNow
            ? "They can sign in now with {$d['email']} and the password you set".($d['role'] === 'staff' ? ' — on the web panel and the mobile app.' : '.')
            : "An email was sent to {$d['email']} to set a password."),
            $d['role'] === 'staff' ? [['Assign them an order', route('erp.orders', ['new' => 1])], ['Add another person', route('erp.users', ['new' => 1])]] : []);
    }

    public function setUserStatus(Request $r, User $user)
    {
        $this->own($user->distributor_id);
        abort_if($user->id === $r->user()->id, 422, 'You cannot change your own status.');
        $status = $r->validate(['status' => 'required|in:active,suspended,inactive'])['status'];
        // Rotating the remember token kills persistent logins; Role middleware rejects live sessions.
        $user->forceFill(['status' => $status, 'remember_token' => Str::random(60)])->save();
        if ($status !== 'active') {
            $user->tokens()->delete(); // sign out of the field app on every device
        }
        AuditLog::record('user.'.$status, $user);

        return $this->done($status === 'active' ? "{$user->name} can sign in again." : "{$user->name} is ".strtolower(Ui::label($status)).' and has been signed out everywhere, including the mobile app.');
    }

    /** Set or clear the mobile number a user signs in with on the field app. */
    public function setUserPhone(Request $r, User $user)
    {
        $this->own($user->distributor_id);
        abort_if($user->hasRole('admin') && ! $r->user()->hasRole('admin'), 403);
        $raw = $r->validate(['phone' => 'nullable|string|max:20'])['phone'] ?? null;
        $phone = null;
        if (filled($raw)) {
            $phone = User::normalizePhone($raw) ?? $this->fail('phone', 'Enter a valid 10-digit mobile number.');
            if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
                $this->fail('phone', 'Someone else already signs in with this mobile number.');
            }
        }
        $user->update(['phone' => $phone]);
        AuditLog::record('user.phone_changed', $user, ['by' => $r->user()->id]);

        return $this->done($phone ? "{$user->name} can now sign in to the app with {$phone} and their password." : "Mobile number removed for {$user->name}.");
    }

    /** Admin/distributor sets a new password for someone in their scope; all their sessions and app tokens end. */
    public function setUserPassword(Request $r, User $user)
    {
        $this->own($user->distributor_id);
        abort_if($user->hasRole('admin') && ! $r->user()->hasRole('admin'), 403);
        $pw = $r->validate(['password' => 'required|string|min:8|max:255|confirmed'])['password'];
        $user->forceFill(['password' => Hash::make($pw), 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        AuditLog::record('user.password_changed', $user, ['by' => $r->user()->id]);

        return $this->done("Password changed for {$user->name}. They have been signed out of the web panel and mobile app, and must use the new password.");
    }

    /** $password null = random unusable password + activation email; otherwise the admin-chosen password (hashed by the model cast). */
    private function makeUser(string $name, string $email, string $role, ?int $distributorId, ?string $password = null): User
    {
        $u = User::create(['name' => $name, 'email' => $email, 'password' => $password ?? Str::random(40), 'role' => $role, 'distributor_id' => $distributorId]);
        if ($password === null) {
            Password::sendResetLink(['email' => $email]);
        }
        AuditLog::record('user.created', $u, ['role' => $role, 'login' => $password === null ? 'invite' : 'password_set']);

        return $u;
    }

    // ---------- Transfers ----------
    public function transfers(Request $r)
    {
        $status = $r->validate(['status' => 'nullable|in:draft,approved,in_transit,partially_received,received'])['status'] ?? null;

        return view('erp.transfers', [
            'transfers' => $this->scopedTransfers()->with('from', 'to', 'lines.product')
                ->when($status, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25)->withQueryString(),
            'statusCounts' => $this->scopedTransfers()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'locations' => Location::whereIn('type', ['warehouse', 'distributor'])->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveTransfer(Request $r)
    {
        $d = $r->validate([
            'from_location_id' => 'required|exists:locations,id', 'to_location_id' => 'required|exists:locations,id',
            'lines' => 'required|array', 'lines.*' => 'nullable|integer|min:0', 'remarks' => 'nullable|max:255',
        ], ['lines.required' => 'Enter a quantity for at least one product.']);
        if ((int) $d['from_location_id'] === (int) $d['to_location_id']) {
            $this->fail('to_location_id', 'Choose a different destination: stock cannot be sent to the place it is already in.');
        }
        if (! self::anyQty($d['lines'])) {
            $this->fail('lines', 'Enter a quantity for at least one product.');
        }
        $t = $this->transfers->create(Location::findOrFail($d['from_location_id']), Location::findOrFail($d['to_location_id']), $d['lines'], $d['remarks'] ?? null);

        // Admins approve their own transfers anyway; "approve now" saves a second click.
        if ($r->boolean('approve_now') && $r->user()->hasRole('admin')) {
            $this->transfers->approve($t);

            return $this->done("Transfer {$t->number} created and approved. Nothing has moved yet: click Dispatch when the goods leave the warehouse.",
                [['Go to dispatch', route('erp.transfers', ['status' => 'approved'])]]);
        }

        return $this->done("Transfer {$t->number} saved as a draft. Nothing has moved yet; an admin must approve it, then it can be dispatched.",
            $r->user()->hasRole('admin') ? [['Approve it', route('erp.transfers', ['status' => 'draft'])]] : []);
    }

    public function transferAction(Request $r, Transfer $transfer, string $action)
    {
        $user = $r->user();
        match ($action) {
            'approve' => abort_unless($user->hasRole('admin'), 403) ?? $this->transfers->approve($transfer),
            'dispatch' => abort_unless($user->hasRole('admin', 'inventory_manager'), 403) ?? $this->transfers->dispatch($transfer),
            'receive' => $this->receiveTransfer($r, $transfer),
            default => abort(404),
        };
        $t = $transfer->fresh('to');

        return match ($action) {
            'approve' => $this->done("Transfer {$t->number} approved. Next: dispatch it when the goods leave.", [['Go to dispatch', route('erp.transfers', ['status' => 'approved'])]]),
            'dispatch' => $this->done("Transfer {$t->number} dispatched. The stock is now in transit until {$t->to->name} receives it."),
            default => $this->done($t->status === 'received'
                ? "Transfer {$t->number} fully received. The stock is now available to sell."
                : "Receipt saved. Part of {$t->number} is still in transit; receive the rest from the same transfer when it arrives.",
                $user->hasRole('distributor') ? [['Take an order', route('erp.orders', ['new' => 1])]] : []),
        };
    }

    private function receiveTransfer(Request $r, Transfer $t): void
    {
        $dest = $t->to;
        $this->own($dest->distributor_id);
        abort_if($r->user()->hasRole('staff', 'viewer', 'accounts_manager'), 403);
        $d = $r->validate(['received' => 'array', 'received.*' => 'nullable|integer|min:0', 'damaged' => 'array', 'damaged.*' => 'nullable|integer|min:0']);
        if (! self::anyQty(array_merge(array_values($d['received'] ?? []), array_values($d['damaged'] ?? [])))) {
            $this->fail('received', 'Enter how many arrived (good or damaged) for at least one product.');
        }
        $this->transfers->receive($t, $d['received'] ?? [], $d['damaged'] ?? []);
    }

    // ---------- Shops ----------
    public function shops()
    {
        return view('erp.shops', ['shops' => $this->scoped(Shop::with('distributor'))->withCount('invoices')
            ->select('shops.*')->selectRaw('(SELECT COALESCE(SUM('.Invoice::OUTSTANDING_SQL.'), 0) FROM invoices WHERE invoices.shop_id = shops.id) as outstanding')
            ->selectRaw('(SELECT MAX(created_at) FROM orders WHERE orders.shop_id = shops.id) as last_order_at')
            ->orderBy('name')->get(),
            'distributors' => Distributor::where('status', 'active')->get()]);
    }

    public function saveShop(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|max:255', 'owner' => 'nullable|max:255', 'phone' => 'nullable|max:30', 'area' => 'nullable|max:100',
            'address' => 'nullable|max:500', 'distributor_id' => 'nullable|exists:distributors,id',
            'credit_limit' => 'nullable|numeric|min:0', 'payment_terms_days' => 'nullable|integer|min:0|max:365',
        ]);
        $d['distributor_id'] = $r->user()->scopedDistributorId() ?? $d['distributor_id'] ?? $this->fail('distributor_id', 'Choose which distributor supplies this shop.');
        // Same name in the same area, or with the same phone, under one distributor is almost always a double entry.
        $dupe = Shop::where('distributor_id', $d['distributor_id'])->whereRaw('LOWER(name) = ?', [Str::lower(trim($d['name']))])
            ->where(fn ($q) => $q->where(fn ($q) => isset($d['area']) ? $q->whereRaw('LOWER(area) = ?', [Str::lower($d['area'])]) : $q->whereNull('area'))
                ->orWhere(fn ($q) => $q->whereNotNull('phone')->where('phone', $d['phone'] ?? '')))
            ->first();
        if ($dupe) {
            $this->fail('name', "\"{$dupe->name}\"".($dupe->area ? " in {$dupe->area}" : '').' already exists. Open it from the shop list instead of adding it again.');
        }
        $shop = Shop::create(array_filter($d, fn ($v) => $v !== null));
        AuditLog::record('shop.created', $shop);

        return $this->done("Shop \"{$shop->name}\" added with {$shop->fresh()->payment_terms_days} days to pay.",
            [['Take an order for this shop', route('erp.orders', ['new' => 1, 'shop' => $shop->id])], ['Add another shop', route('erp.shops', ['new' => 1])]]);
    }

    public function shop(Shop $shop)
    {
        $this->own($shop->distributor_id);
        $invoices = $shop->invoices()->with('allocations', 'creditNotes')->latest('id')->get();

        return view('erp.shop', ['shop' => $shop, 'invoices' => $invoices,
            'payments' => Payment::where('shop_id', $shop->id)->with('allocations', 'collector')->latest('id')->get(),
            'orders' => Order::where('shop_id', $shop->id)->with('staff')->latest('id')->limit(8)->get(),
            'outstanding' => $invoices->reduce(fn ($c, $i) => bcadd($c, $i->outstanding(), 2), '0')]);
    }

    // ---------- Orders & deliveries ----------
    public function orders(Request $r)
    {
        $f = $r->validate(['status' => 'nullable|in:open,confirmed,packed,out_for_delivery,delivered,partially_delivered,cancelled,failed', 'q' => 'nullable|string|max:100']);
        $q = $this->scoped(Order::with('shop', 'lines.product', 'invoice', 'staff'))->latest('id');
        if (auth()->user()->hasRole('staff')) {
            $q->where(fn ($q) => $q->where('staff_id', auth()->id())->orWhere('created_by', auth()->id()));
        }
        $counts = (clone $q)->reorder()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $q->when($f['status'] ?? null, fn ($q, $s) => $s === 'open' ? $q->whereIn('status', ['confirmed', 'packed', 'out_for_delivery']) : $q->where('status', $s))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")->orWhereHas('shop', fn ($sh) => $sh->where('name', 'like', "%{$s}%"))));

        return view('erp.orders', ['orders' => $q->paginate(25)->withQueryString(), 'statusCounts' => $counts,
            'shops' => $this->scoped(Shop::where('status', 'active'))->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'staff' => $this->scoped(User::where('role', 'staff')->where('status', 'active'))->get()]);
    }

    public function saveOrder(Request $r)
    {
        $d = $r->validate(['shop_id' => 'required|exists:shops,id', 'staff_id' => 'nullable|exists:users,id',
            'lines' => 'required|array', 'lines.*' => 'nullable|integer|min:0', 'remarks' => 'nullable|max:500', 'key' => 'nullable|string|max:64'],
            ['shop_id.required' => 'Choose the shop that is ordering.', 'lines.required' => 'Enter a quantity for at least one product.']);
        $shop = Shop::findOrFail($d['shop_id']);
        $this->own($shop->distributor_id);
        $staffId = $r->user()->hasRole('staff') ? $r->user()->id : ($d['staff_id'] ?? null);
        if ($staffId) {
            abort_unless(User::where('id', $staffId)->where('distributor_id', $shop->distributor_id)->exists(), 422, 'Staff belongs to another distributor.');
        }
        // Double-click / back-and-resubmit protection: the form's one-time key is stored on the order.
        if (! empty($d['key']) && $existing = Order::where('created_by', $r->user()->id)->where('idempotency_key', $d['key'])->first()) {
            return $this->done("Order {$existing->number} was already saved, so it was not created twice.");
        }
        if (! self::anyQty($d['lines'])) {
            $this->fail('lines', 'Enter a quantity for at least one product.');
        }
        $o = $this->sales->createOrder($shop, $d['lines'], $staffId, array_filter(['remarks' => $d['remarks'] ?? null, 'idempotency_key' => $d['key'] ?? null]));

        return $this->done("Order {$o->number} for {$shop->name} confirmed, total ".\App\Support\Fmt::money($o->total).'. The stock is set aside for this shop. Next: deliver it and record the delivery.',
            [['Record delivery', route('erp.orders', ['status' => 'open']).'#order-'.$o->id], ['New order', route('erp.orders', ['new' => 1])]]);
    }

    public function orderAction(Request $r, Order $order, string $action)
    {
        $u = $r->user();
        $this->own($order->distributor_id);
        abort_if($u->hasRole('viewer', 'accounts_manager', 'inventory_manager'), 403);
        abort_if($u->hasRole('staff') && $order->staff_id !== $u->id, 403);
        if ($action === 'failed' && ! $r->filled('reason')) {
            $this->fail('reason', 'Write why the delivery failed, for example "Shop closed" or "Refused by owner".');
        }

        match ($action) {
            'packed', 'out_for_delivery' => $this->sales->setStatus($order, $action),
            'deliver' => $this->sales->deliver($order, $r->validate(['delivered' => 'required|array', 'delivered.*' => 'integer|min:0'])['delivered']),
            'cancelled', 'failed' => $this->sales->cancel($order, $action, $r->input('reason')),
            'return' => $this->returnLine($r, $order),
            default => abort(404),
        };
        $o = $order->fresh(['invoice', 'shop']);

        return match ($action) {
            'deliver' => $this->done("Delivery recorded for {$o->number}. Stock deducted and invoice {$o->invoice->number} created for "
                .\App\Support\Fmt::money($o->invoice->total).", due on {$o->invoice->due_date->format('d M Y')}.",
                [['Record payment now', route('erp.shop', $o->shop).'#collect'], ['View / print invoice', route('erp.invoice', $o->invoice)]]),
            'return' => $this->done("Return recorded for {$o->number}. Good items are back in stock and a credit note reduced what {$o->shop->name} owes."),
            'cancelled', 'failed' => $this->done("Order {$o->number} is ".strtolower(\App\Support\Fmt::status($action)[1]).'. The reserved stock is available again.'),
            default => $this->done("Order {$o->number} marked ".strtolower(\App\Support\Fmt::status($action)[1]).'.'),
        };
    }

    private function returnLine(Request $r, Order $order): void
    {
        abort_if($r->user()->hasRole('staff'), 403);
        $d = $r->validate(['line_id' => 'required|integer', 'sellable' => 'required|integer|min:0', 'damaged' => 'required|integer|min:0', 'reason' => 'required|max:255'],
            ['reason.required' => 'Write why the shop returned the goods.']);
        if ((int) $d['sellable'] + (int) $d['damaged'] <= 0) {
            $this->fail('sellable', 'Enter how many came back in good condition and/or damaged.');
        }
        $this->sales->processReturn($order, (int) $d['line_id'], (int) $d['sellable'], (int) $d['damaged'], $d['reason']);
    }

    // ---------- Invoices & payments ----------
    public function invoices(Request $r)
    {
        $f = $r->validate(['status' => 'nullable|in:unpaid,overdue,paid', 'q' => 'nullable|string|max:100']);
        $base = fn () => $this->scoped(Invoice::query());
        $dues =$base()->unpaid()->selectRaw('id, due_date, '.Invoice::OUTSTANDING_SQL.' as due')->get();
        $aging = ['Not due' => 0, '1–30' => 0, '31–60' => 0, '61–90' => 0, '90+' => 0];
        foreach ($dues as $i) {
            $late = (int) $i->due_date->diffInDays(today(), false);
            $aging[$late <= 0 ? 'Not due' : ($late <= 30 ? '1–30' : ($late <= 60 ? '31–60' : ($late <= 90 ? '61–90' : '90+')))] += (float) $i->due;
        }
        $q = $this->scoped(Invoice::with('shop', 'allocations', 'creditNotes'))->select('invoices.*')->selectRaw(Invoice::OUTSTANDING_SQL.' as due')
            ->when($f['status'] ?? null, fn ($q, $s) => match ($s) { 'unpaid' => $q->unpaid(), 'overdue' => $q->overdue(), 'paid' => $q->whereRaw(Invoice::OUTSTANDING_SQL.' <= 0.004') })
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")->orWhereHas('shop', fn ($sh) => $sh->where('name', 'like', "%{$s}%"))))
            ->latest('id');

        return view('erp.invoices', ['invoices' => $q->paginate(50)->withQueryString(), 'aging' => $aging,
            'totals' => ['invoiced' => (float) $base()->sum('total'), 'outstanding' => (float) $dues->sum('due'),
                'overdue' => (float) $dues->filter(fn ($i) => $i->due_date->lt(today()))->sum('due'), 'openCount' => $dues->count(),
                'overdueCount' => $dues->filter(fn ($i) => $i->due_date->lt(today()))->count()]]);
    }

    public function invoice(Invoice $invoice)
    {
        $this->own($invoice->distributor_id);

        return view('erp.invoice', ['invoice' => $invoice->load('shop', 'allocations', 'creditNotes'), 'order' => Order::with('lines.product', 'distributor')->find($invoice->order_id)]);
    }

    public function payments(Request $r)
    {
        $f = $r->validate(['status' => 'nullable|in:collected,verified', 'method' => ['nullable', Rule::in(Payment::METHODS)], 'q' => 'nullable|string|max:100']);
        $q = $this->scoped(Payment::with('shop', 'allocations', 'collector'))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$s}%")->orWhere('receipt_number', 'like', "%{$s}%")
                ->orWhereHas('shop', fn ($sh) => $sh->where('name', 'like', "%{$s}%"))))
            ->latest('id');
        $sum = fn ($s) => (float) $this->scoped(Payment::where('status', $s))->sum('amount');

        return view('erp.payments', ['payments' => $q->paginate(50)->withQueryString(),
            'shops' => $this->scoped(Shop::where('status', 'active'))->orderBy('name')->get(['id', 'name', 'area']),
            'totals' => ['pending' => $sum('collected'), 'verified' => $sum('verified'),
                'pendingCount' => $this->scoped(Payment::where('status', 'collected'))->count(),
                'month' => (float) $this->scoped(Payment::where('created_at', '>=', now()->startOfMonth()))->sum('amount')]]);
    }

    public function savePayment(Request $r)
    {
        abort_if($r->user()->hasRole('viewer', 'inventory_manager'), 403);
        $d = $r->validate(['shop_id' => 'required|exists:shops,id', 'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(Payment::METHODS)], 'reference' => 'nullable|max:100',
            'alloc' => 'array', 'alloc.*' => 'nullable|numeric|min:0', 'remarks' => 'nullable|max:500', 'key' => 'nullable|string|max:64'],
            ['amount.min' => 'Enter the amount received (more than 0).', 'shop_id.required' => 'Choose the shop that paid.']);
        $shop = Shop::findOrFail($d['shop_id']);
        $this->own($shop->distributor_id);
        if (in_array($d['method'], config('fieldapp.collections.reference_required', []), true) && blank($d['reference'] ?? null)) {
            $this->fail('reference', 'Enter the '.Ui::label($d['method']).' reference (UTR / cheque number) so the payment can be verified.');
        }
        if (! empty($d['key']) && $existing = Payment::where('collected_by', $r->user()->id)->where('idempotency_key', $d['key'])->first()) {
            return $this->done("Payment {$existing->receipt_number} was already saved, so it was not recorded twice.");
        }
        $amount = number_format((float) $d['amount'], 2, '.', '');
        $alloc = $r->boolean('auto') ? $this->oldestFirst($shop, $amount) : ($d['alloc'] ?? []);
        $p = $this->sales->recordPayment($shop, $amount, $d['method'], $d['reference'] ?? null, $alloc,
            array_filter(['remarks' => $d['remarks'] ?? null, 'idempotency_key' => $d['key'] ?? null]));
        $left = bcsub($amount, (string) $p->allocations()->sum('amount'), 2);
        $staff = $r->user()->hasRole('staff');

        return $this->done("Payment of ".\App\Support\Fmt::money($amount)." from {$shop->name} recorded, receipt {$p->receipt_number}. "
            .(bccomp($left, '0', 2) > 0 ? \App\Support\Fmt::money($left).' was not set against any invoice and is kept as an advance. ' : '')
            .($staff ? 'Hand the money to your manager, who will verify it.' : 'Next: verify it once the money is in your cash box or bank.'),
            $staff ? [] : [['Verify payments', route('erp.payments', ['status' => 'collected'])]]);
    }

    /** Pay the shop's oldest unpaid invoices first: [invoice_id => amount]. */
    private function oldestFirst(Shop $shop, string $amount): array
    {
        $left = $amount;
        $alloc = [];
        foreach (Invoice::where('shop_id', $shop->id)->unpaid()->orderBy('due_date')->orderBy('id')->get() as $inv) {
            if (bccomp($left, '0', 2) <= 0) {
                break;
            }
            $owed = $inv->outstanding();
            $take = bccomp($owed, $left, 2) < 0 ? $owed : $left;
            $alloc[$inv->id] = $take;
            $left = bcsub($left, $take, 2);
        }

        return $alloc;
    }

    public function verifyPayment(Request $r, Payment $payment)
    {
        $this->own($payment->distributor_id);
        abort_unless($r->user()->hasRole('admin', 'accounts_manager', 'distributor'), 403);
        $this->sales->verifyPayment($payment);

        return $this->done('Payment '.$payment->receipt_number.' of '.\App\Support\Fmt::money($payment->amount).' verified.');
    }

    // ---------- Field staff: attendance & expenses ----------
    public function attendance(Request $r)
    {
        $d = $r->validate(['date' => 'nullable|date_format:Y-m-d', 'user_id' => 'nullable|integer']);
        $q = $this->scoped(Attendance::with('user', 'distributor'))
            ->when($d['date'] ?? null, fn ($q, $v) => $q->whereDate('date', $v))
            ->when($d['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->orderByDesc('date')->orderBy('user_id');

        return view('erp.attendance', ['rows' => $q->paginate(50)->withQueryString(),
            'staff' => $this->scoped(User::where('role', 'staff'))->orderBy('name')->get()]);
    }

    public function expenses(Request $r)
    {
        $status = $r->validate(['status' => 'nullable|in:submitted,approved,rejected'])['status'] ?? null;

        return view('erp.expenses', ['expenses' => $this->scoped(Expense::with('user', 'distributor', 'reviewer'))
            ->when($status, fn ($q, $v) => $q->where('status', $v))->latest('id')->paginate(50)->withQueryString(),
            'summary' => $this->scoped(Expense::query())->selectRaw('status, count(*) as n, sum(amount) as total')->groupBy('status')->get()->keyBy('status')]);
    }

    public function reviewExpense(Request $r, Expense $expense, ExpenseService $svc)
    {
        $this->own($expense->distributor_id);
        $d = $r->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|max:500']);
        $svc->review($expense, $d['decision'], $d['note'] ?? null);

        return $this->done('Expense of '.\App\Support\Fmt::money($expense->amount).' by '.$expense->user->name.' '.$d['decision'].'.');
    }

    public function expenseReceipt(Expense $expense)
    {
        $this->own($expense->distributor_id);
        abort_unless($expense->receipt_path, 404);

        return Storage::disk(config('fieldapp.expenses.receipt_disk'))->download($expense->receipt_path, 'receipt-'.$expense->id.'.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION));
    }

    public function setGeofence(Request $r, Distributor $distributor)
    {
        $d = $r->validate([
            'latitude' => 'nullable|required_with:longitude,geofence_radius_m|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude,geofence_radius_m|numeric|between:-180,180',
            'geofence_radius_m' => 'nullable|required_with:latitude|integer|min:20|max:50000',
        ]);
        $distributor->update($d + ['latitude' => null, 'longitude' => null, 'geofence_radius_m' => null]);
        AuditLog::record('distributor.geofence', $distributor, $d);

        return $this->done(isset($d['latitude']) ? "Attendance office area saved for {$distributor->business_name}." : "Attendance office area removed for {$distributor->business_name}.");
    }

    // ---------- Audit ----------
    public function audit(Request $r)
    {
        return view('erp.audit', ['logs' => AuditLog::with('user')->latest('id')
            ->when($r->action, fn ($q, $v) => $q->where('action', 'like', $v.'%'))->paginate(50)->withQueryString()]);
    }
}
