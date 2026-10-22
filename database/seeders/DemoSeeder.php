<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\ExpenseService;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Realistic demo company: ~45 days of pharma distribution history, posted through the
 * real services day by day (Carbon::setTestNow) so every balance, invoice and audit
 * entry is exactly what the app itself would have produced.
 *
 *   php artisan db:seed --class=DemoSeeder        (all logins use password DemoSeeder::PASSWORD)
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';

    public const DAYS = 45;

    private StockService $stock;

    private TransferService $tr;

    private SalesService $sales;

    private ExpenseService $exp;

    private AttendanceService $att;

    /** @var array<int, list<callable>> day index => events */
    private array $events = [];

    private Carbon $start;

    private User $admin;

    private User $inventory;

    private array $owners = [];

    private array $staff = [];

    private array $shops = [];

    private array $locs = [];

    public function run(): void
    {
        if (User::where('email', 'admin@slpharma.test')->exists()) {
            $this->command?->warn('Demo data already present (admin@slpharma.test exists); nothing seeded.');

            return;
        }
        [$this->stock, $this->tr, $this->sales, $this->exp, $this->att] =
            [app(StockService::class), app(TransferService::class), app(SalesService::class), app(ExpenseService::class), app(AttendanceService::class)];
        mt_srand(20261017);
        $this->start = Carbon::today()->subDays(self::DAYS);

        try {
            $this->at(0, 9);
            $this->people();
            [$whMumbai, $whPune, $products] = $this->catalogue();
            $this->network();
            $this->openingTransfers($whMumbai, $whPune, $products);
            $this->simulate($products);
            $this->tail($whMumbai, $whPune, $products);
        } finally {
            Carbon::setTestNow();
            Auth::guard()->forgetUser();
        }

        $this->command?->info('Demo data ready. Logins (password '.self::PASSWORD.'):');
        foreach ($this->logins() as [$role, $email]) {
            $this->command?->line("  {$role}: {$email}");
        }
    }

    /** @return list<array{0:string, 1:string}> */
    public function logins(): array
    {
        return [
            ['Admin', 'admin@slpharma.test'], ['Inventory manager', 'inventory@slpharma.test'], ['Accounts manager', 'accounts@slpharma.test'],
            ['Viewer (auditor)', 'auditor@slpharma.test'], ['Distributor owner', 'owner.sharma@slpharma.test'], ['Field staff', 'ravi.kumar@slpharma.test'],
        ];
    }

    // ------------------------------------------------------------------ setup

    private function at(int $day, int $hour, int $min = 0): void
    {
        Carbon::setTestNow($this->start->copy()->addDays($day)->setTime($hour, $min));
    }

    private function as(User $u): void
    {
        Auth::guard()->setUser($u);
    }

    private function user(string $name, string $email, string $role, ?int $did = null): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'role' => $role, 'distributor_id' => $did]);
    }

    private function people(): void
    {
        $this->admin = $this->user('Priya Nair', 'admin@slpharma.test', 'admin');
        $this->as($this->admin);
        $this->inventory = $this->user('Suresh Iyer', 'inventory@slpharma.test', 'inventory_manager');
        $this->user('Kavita Desai', 'accounts@slpharma.test', 'accounts_manager');
        $this->user('Rohan Gupta', 'auditor@slpharma.test', 'viewer');
    }

    /** @return array{0: Location, 1: Location, 2: list<Product>} */
    private function catalogue(): array
    {
        // [name, sku, category, unit, selling price (INR), GST %, low-stock level, Mumbai opening, Pune opening]
        $rows = [
            ['Paracetamol 500mg (10 tabs)', 'PARA500', 'Analgesics', 'strip', 18, 12, 300, 4000, 1500],
            ['Ibuprofen 400mg (10 tabs)', 'IBU400', 'Analgesics', 'strip', 32, 12, 200, 2500, 800],
            ['Diclofenac Gel 30g', 'DICLO30', 'Analgesics', 'tube', 95, 12, 80, 900, 300],
            ['Amoxicillin 500mg (10 caps)', 'AMOX500', 'Antibiotics', 'strip', 85, 12, 150, 1800, 600],
            ['Azithromycin 500mg (3 tabs)', 'AZI500', 'Antibiotics', 'strip', 72, 12, 150, 1500, 500],
            ['Cefixime 200mg (10 tabs)', 'CEF200', 'Antibiotics', 'strip', 110, 12, 300, 520, 0],
            ['Pantoprazole 40mg (15 tabs)', 'PAN40', 'Gastro care', 'strip', 65, 12, 200, 2200, 700],
            ['ORS Electrolyte Sachet 21g', 'ORS21', 'Gastro care', 'pack', 20, 5, 400, 5000, 2000],
            ['Antacid Gel 170ml', 'ANTAC170', 'Gastro care', 'bottle', 115, 12, 100, 1100, 400],
            ['Vitamin C 500mg (15 tabs)', 'VITC500', 'Vitamins & supplements', 'strip', 45, 18, 200, 2400, 800],
            ['Multivitamin Capsules (30)', 'MULTI30', 'Vitamins & supplements', 'bottle', 150, 18, 120, 1200, 400],
            ['Calcium + Vitamin D3 (15 tabs)', 'CALD3', 'Vitamins & supplements', 'strip', 120, 12, 150, 1500, 500],
            ['Cough Syrup 100ml', 'COUGH100', 'Cough & cold', 'bottle', 98, 12, 150, 1600, 600],
            ['Cetirizine 10mg (10 tabs)', 'CET10', 'Cough & cold', 'strip', 25, 12, 250, 3000, 1000],
            ['Metformin 500mg (20 tabs)', 'MET500', 'Diabetes care', 'strip', 30, 12, 250, 2600, 900],
            ['Glucometer Test Strips (50)', 'GLUC50', 'Diabetes care', 'box', 750, 12, 80, 160, 0],
            ['Hand Sanitizer 500ml', 'SANI500', 'Personal care', 'bottle', 210, 18, 60, 700, 250],
            ['Surgical Masks (box of 50)', 'MASK50', 'Personal care', 'box', 180, 5, 100, 1000, 300],
        ];
        $this->as($this->inventory);
        $wm = Location::create(['name' => 'Mumbai Central Warehouse', 'type' => 'warehouse']);
        $wp = Location::create(['name' => 'Pune Warehouse', 'type' => 'warehouse']);
        $products = [];
        foreach ($rows as $i => [$name, $sku, $cat, $unit, $price, $gst, $reorder, $openM, $openP]) {
            $p = Product::create(['name' => $name, 'sku' => $sku, 'category' => $cat, 'unit' => $unit, 'selling_price' => $price,
                'cost_price' => round($price * 0.68, 2), 'tax_rate' => $gst, 'reorder_level' => $reorder, 'active' => true]);
            $this->stock->move($p->id, Location::system('external'), $wm, $openM, 'opening', "demo-open-m-{$p->id}", null, 'Opening stock count');
            if ($openP) {
                $this->stock->move($p->id, Location::system('external'), $wp, $openP, 'opening', "demo-open-p-{$p->id}", null, 'Opening stock count');
            }
            $products[] = $p;
        }
        // One discontinued product, kept for history.
        Product::create(['name' => 'Cold Rub Balm 25g (discontinued)', 'sku' => 'BALM25', 'category' => 'Cough & cold', 'unit' => 'jar',
            'selling_price' => 60, 'cost_price' => 40, 'tax_rate' => 12, 'reorder_level' => 0, 'active' => false]);

        return [$wm, $wp, $products];
    }

    private function network(): void
    {
        $this->as($this->admin);
        $dists = [
            ['Sharma Distributors', 'Anil Sharma', 'sharma', 'Andheri, Mumbai', 19.1197, 72.8468,
                [['Ravi Kumar', 'ravi.kumar'], ['Sunita Rao', 'sunita.rao']],
                [['City Medical', 'Andheri West'], ['Apna Chemist', 'Andheri East'], ['Wellness Pharmacy', 'Jogeshwari'], ['Shree Medicals', 'Vile Parle'],
                    ['Om Sai Medical', 'Andheri West'], ['Lifeline Chemists', 'Goregaon'], ['Health Plus', 'Versova'], ['New Ganesh Medical', 'Marol']]],
            ['Patel Pharma Agencies', 'Hitesh Patel', 'patel', 'Pune', 18.5204, 73.8567,
                [['Amit Joshi', 'amit.joshi'], ['Neha Kulkarni', 'neha.kulkarni']],
                [['Pune Medico', 'Shivajinagar'], ['Deccan Chemist', 'Deccan'], ['Kothrud Medical', 'Kothrud'], ['Sanjeevani Pharmacy', 'Baner'],
                    ['Aundh Medicals', 'Aundh'], ['Hadapsar Health', 'Hadapsar'], ['Shree Ram Medical', 'Kothrud'], ['MedPoint', 'Viman Nagar']]],
            ['Mehta Medico', 'Rakesh Mehta', 'mehta', 'Thane', 19.2183, 72.9781,
                [['Vikas Shinde', 'vikas.shinde'], ['Pooja Pawar', 'pooja.pawar']],
                [['Thane Chemists', 'Naupada'], ['Ghodbunder Medical', 'Ghodbunder'], ['Jai Ambe Pharmacy', 'Wagle Estate'], ['CarePlus', 'Majiwada'],
                    ['Vartak Nagar Medicals', 'Vartak Nagar'], ['Sai Krupa Medical', 'Kopri'], ['Medicare Hub', 'Kalwa'], ['Lokmanya Chemist', 'Lokmanya Nagar']]],
            ['Krishna Health Distributors', 'Gopal Krishnan', 'krishna', 'Navi Mumbai', 19.0330, 73.0297,
                [['Arjun Menon', 'arjun.menon'], ['Fatima Shaikh', 'fatima.shaikh']],
                [['Vashi Medical', 'Vashi'], ['Nerul Chemist', 'Nerul'], ['Belapur Pharmacy', 'CBD Belapur'], ['Kharghar Medicals', 'Kharghar'],
                    ['Airoli Health', 'Airoli'], ['Sanpada Medical', 'Sanpada'], ['Panvel Medico', 'Panvel'], ['Ghansoli Chemists', 'Ghansoli']]],
        ];
        $terms = [7, 15, 15, 30];
        foreach ($dists as $k => [$biz, $contact, $slug, $area, $lat, $lng, $staff, $shops]) {
            $d = Distributor::create(['business_name' => $biz, 'contact_person' => $contact, 'phone' => '98200'.str_pad((string) (11111 * ($k + 1)), 5, '0'),
                'email' => "owner.{$slug}@slpharma.test", 'area' => $area, 'address' => "Office 1{$k}, Main Road, {$area}", 'credit_limit' => 500000,
                'latitude' => $lat, 'longitude' => $lng, 'geofence_radius_m' => 300]);
            $this->locs[$d->id] = Location::create(['name' => $biz, 'type' => 'distributor', 'distributor_id' => $d->id]);
            $this->owners[$d->id] = $this->user($contact, "owner.{$slug}@slpharma.test", 'distributor', $d->id);
            foreach ($staff as [$n, $e]) {
                $this->staff[$d->id][] = $this->user($n, "{$e}@slpharma.test", 'staff', $d->id);
            }
            foreach ($shops as $j => [$name, $shopArea]) {
                $this->shops[$d->id][] = Shop::create(['name' => $name, 'owner' => ['Mr. Patil', 'Mrs. Shah', 'Mr. Khan', 'Mr. D\'Souza', 'Mrs. Iyer', 'Mr. Jain'][($j + $k) % 6],
                    'phone' => '9' . str_pad((string) (300000000 + $k * 1000 + $j * 37), 9, '0'), 'area' => $shopArea, 'address' => "Shop {$j}, {$shopArea}",
                    'distributor_id' => $d->id, 'credit_limit' => [25000, 50000, 75000][$j % 3], 'payment_terms_days' => $terms[($j + $k) % 4]]);
            }
        }
        // A staff member who left: suspended, history kept.
        $this->user('Deepak Verma', 'deepak.verma@slpharma.test', 'staff', array_key_first($this->owners))->update(['status' => 'suspended']);
    }

    private function send(Location $from, Location $to, array $lines, string $remarks, int $day, ?int $receiveDay, array $damaged = [], array $partial = []): void
    {
        // Never plan more than the source has free (what a dispatcher checks before loading).
        $lines = array_filter($lines, fn ($q, $pid) => $q > 0 && $this->stock->available($from, $pid) >= $q, ARRAY_FILTER_USE_BOTH);
        if (! $lines) {
            return;
        }
        $this->at($day, 10);
        $this->as($this->inventory);
        $t = $this->tr->create($from, $to, $lines, $remarks);
        $this->as($this->admin);
        $this->tr->approve($t);
        $this->at($day, 15);
        $this->as($this->inventory);
        $this->tr->dispatch($t);
        if ($receiveDay === null) {
            return;
        }
        $this->at($receiveDay, 11);
        $this->as($this->owners[$to->distributor_id]);
        $t->load('lines');
        $recv = $dmg = [];
        foreach ($t->lines as $l) {
            $dmg[$l->id] = $damaged[$l->product_id] ?? 0;
            $recv[$l->id] = $l->qty - $dmg[$l->id] - ($partial[$l->product_id] ?? 0);
        }
        $this->tr->receive($t, $recv, $dmg);
        if ($partial) { // the balance arrives two days later
            $this->at($receiveDay + 2, 11);
            $t->load('lines');
            $this->tr->receive($t, collect($t->lines)->mapWithKeys(fn ($l) => [$l->id => $l->qty - $l->received_qty - $l->damaged_qty])->all());
        }
    }

    /** @param list<Product> $products */
    private function openingTransfers(Location $wm, Location $wp, array $products): void
    {
        $i = 0;
        foreach ($this->locs as $did => $loc) {
            $lines = [];
            foreach ($products as $p) {
                $lines[$p->id] = match ($p->sku) { 'GLUC50' => 15, 'CEF200' => 40, 'SANI500' => 60, default => 120 + 20 * mt_rand(0, 8) };
            }
            $from = $loc->name === 'Patel Pharma Agencies' ? $wp : $wm;
            $first = $products[0]->id;
            // Mehta's first truck: 6 strips damaged and 40 arriving late (partial receipt).
            $mehta = $loc->name === 'Mehta Medico';
            $this->send($from, $loc, $lines, 'Opening supply · Vehicle MH-04-AB-'.(1200 + $i++), 1, 2,
                $mehta ? [$first => 6] : [], $mehta ? [$products[3]->id => 40] : []);
        }
    }

    // ------------------------------------------------------------------ daily simulation

    /** @param list<Product> $products */
    private function simulate(array $products): void
    {
        for ($day = 3; $day <= self::DAYS; $day++) {
            $date = $this->start->copy()->addDays($day);
            $sunday = $date->isSunday();
            foreach ($this->events[$day] ?? [] as $e) {
                try {
                    $e();
                } catch (Throwable $ex) {
                    // A demo event can lose a race with stock (e.g. restock arrived later); skip it like a user would.
                }
            }
            if ($day === 22) { // mid-period restock for everyone
                foreach ($this->locs as $loc) {
                    $lines = [];
                    foreach ($products as $p) {
                        if (! in_array($p->sku, ['GLUC50', 'CEF200'], true) && $this->stock->available($loc, $p->id) < 100) {
                            $lines[$p->id] = 150;
                        }
                    }
                    if ($lines) {
                        $this->send(Location::where('name', 'Mumbai Central Warehouse')->first(), $loc, $lines, 'Restock', 22, 23);
                    }
                }
            }
            if ($sunday) {
                continue;
            }
            foreach ($this->staff as $did => $team) {
                foreach ($team as $s) {
                    $this->attendance($s, $did, $day);
                    for ($n = mt_rand(0, 3); $n > 0; $n--) {
                        $this->order($s, $did, $day, $products);
                    }
                    if (mt_rand(1, 3) === 1) {
                        $this->expense($s, $did, $day);
                    }
                }
            }
        }
    }

    private function attendance(User $s, int $did, int $day): void
    {
        $d = Distributor::find($did);
        $far = mt_rand(1, 10) === 1; // ~10% check-ins away from the office
        $loc = fn () => ['latitude' => $d->latitude + ($far ? 0.02 : mt_rand(-10, 10) / 10000), 'longitude' => $d->longitude + ($far ? 0.015 : mt_rand(-10, 10) / 10000), 'accuracy_m' => mt_rand(5, 40)];
        $this->as($s);
        try {
            $this->at($day, 9, mt_rand(5, 50));
            $this->att->checkIn($s, $loc());
            if ($day < self::DAYS || mt_rand(0, 1)) {
                $this->at($day, 18, mt_rand(0, 55));
                $this->att->checkOut($s, $loc() + ['remarks' => $day % 7 === 0 ? 'Covered extra beat today' : null]);
            }
        } catch (Throwable) {
            // geofence "enforce" mode may reject the far check-ins; that is realistic too.
        }
    }

    /** @param list<Product> $products */
    private function order(User $s, int $did, int $day, array $products): void
    {
        $loc = $this->locs[$did];
        $shop = $this->shops[$did][mt_rand(0, count($this->shops[$did]) - 1)];
        $lines = [];
        foreach ((array) array_rand($products, mt_rand(1, 4)) as $i) {
            $p = $products[$i];
            $q = min(mt_rand(3, 24), $this->stock->available($loc, $p->id));
            if ($q > 0) {
                $lines[$p->id] = $q;
            }
        }
        if (! $lines) {
            return;
        }
        $this->at($day, mt_rand(10, 16), mt_rand(0, 59));
        $this->as($s);
        try {
            $o = $this->sales->createOrder($shop, $lines, $s->id, mt_rand(1, 6) === 1 ? ['remarks' => 'Urgent: deliver before noon'] : []);
        } catch (Throwable) {
            return;
        }
        $age = self::DAYS - $day;
        $roll = mt_rand(1, 100);
        if ($age === 0) {
            return; // today's orders stay confirmed
        }
        if ($age === 1 && $roll <= 60) { // yesterday's: some packed / out for delivery
            $this->event($day + 1, 9, $s, fn () => $this->sales->setStatus($o, 'packed'));
            if ($roll <= 30) {
                $this->event($day + 1, 10, $s, fn () => $this->sales->setStatus($o->fresh(), 'out_for_delivery'));
            }

            return;
        }
        if ($roll <= 5) {
            $this->event($day + 1, 12, $s, fn () => $this->sales->cancel($o, 'failed', ['Shop closed', 'Owner refused delivery', 'Wrong address'][mt_rand(0, 2)]));

            return;
        }
        if ($roll <= 9) {
            $this->event($day + 1, 9, $this->owners[$did], fn () => $this->sales->cancel($o, 'cancelled', 'Shop cancelled the order'));

            return;
        }
        $this->event($day + 1, mt_rand(10, 13), $s, function () use ($o, $s, $did, $day) {
            $this->sales->setStatus($o, 'packed');
            $this->sales->setStatus($o->fresh(), 'out_for_delivery');
            $o->load('lines');
            $short = mt_rand(1, 10) === 1; // ~10% partly delivered
            $inv = $this->sales->deliver($o, $o->lines->mapWithKeys(fn ($l, $i) => [$l->id => $short && $i === 0 ? max(1, intdiv($l->qty, 2)) : $l->qty])->all());
            $this->collect($inv, $s, $did, $day + 1, $o->shop->payment_terms_days);
        });
    }

    private function collect($inv, User $s, int $did, int $deliveredDay, int $terms): void
    {
        $roll = mt_rand(1, 100);
        if ($roll > 85) {
            return; // never paid (yet): shows as outstanding / overdue
        }
        $payDay = $deliveredDay + mt_rand(1, $terms + 12);
        if ($payDay > self::DAYS) {
            return;
        }
        $partial = $roll > 75;
        $this->event($payDay, mt_rand(11, 17), $s, function () use ($inv, $s, $did, $payDay, $partial) {
            $amount = $partial ? number_format(floor((float) $inv->outstanding() * 0.5), 2, '.', '') : $inv->outstanding();
            if (bccomp($amount, '0', 2) <= 0) {
                return;
            }
            $method = ['cash', 'cash', 'cash', 'upi', 'upi', 'upi', 'cheque', 'bank_transfer'][mt_rand(0, 7)];
            $p = $this->sales->recordPayment($inv->shop, $amount, $method, $method === 'cash' ? null : strtoupper(substr($method, 0, 3)).mt_rand(100000000, 999999999),
                [$inv->id => $amount], mt_rand(1, 5) === 1 ? ['remarks' => 'Collected at counter'] : []);
            $verifyDay = $payDay + mt_rand(1, 2);
            if ($verifyDay < self::DAYS) { // the last day or two stay unverified
                $this->event($verifyDay, 19, $this->owners[$did], fn () => $this->sales->verifyPayment($p));
            }
        });
    }

    private function expense(User $s, int $did, int $day): void
    {
        $cat = ['fuel', 'fuel', 'food', 'travel', 'courier', 'phone', 'stationery'][mt_rand(0, 6)];
        $amount = ['fuel' => mt_rand(150, 600), 'food' => mt_rand(80, 250), 'travel' => mt_rand(60, 400), 'courier' => mt_rand(40, 150), 'phone' => 299, 'stationery' => mt_rand(50, 200)][$cat];
        $desc = ['fuel' => 'Bike fuel for beat', 'food' => 'Lunch on field day', 'travel' => 'Auto fare to shops', 'courier' => 'Courier to depot', 'phone' => 'Monthly mobile recharge', 'stationery' => 'Order book and pens'][$cat];
        $this->at($day, 19);
        $this->as($s);
        $e = $this->exp->submit($s, ['category' => $cat, 'amount' => $amount, 'expense_date' => $this->start->copy()->addDays($day)->toDateString(), 'description' => $desc]);
        if (self::DAYS - $day >= 2) {
            $reject = mt_rand(1, 7) === 1;
            $this->event($day + mt_rand(1, 2), 20, $this->owners[$did],
                fn () => $this->exp->review($e, $reject ? 'rejected' : 'approved', $reject ? 'No receipt attached; please resubmit with the bill.' : null));
        }
    }

    private function event(int $day, int $hour, User $actor, callable $fn): void
    {
        $day = min($day, self::DAYS);
        $this->events[$day][] = function () use ($day, $hour, $actor, $fn) {
            $this->at($day, $hour, mt_rand(0, 59));
            $this->as($actor);
            $fn();
        };
    }

    // ------------------------------------------------------------------ "today" state for the dashboard

    /** @param list<Product> $products */
    private function tail(Location $wm, Location $wp, array $products): void
    {
        $locs = array_values($this->locs);

        // A shop returned goods: 2 back to stock, 1 damaged, credit note issued.
        $this->at(self::DAYS - 3, 12);
        $o = Order::where('status', 'delivered')->whereHas('lines', fn ($q) => $q->where('delivered_qty', '>=', 3))->with('lines')->oldest('id')->first();
        if ($o) {
            $this->as($this->owners[$o->distributor_id]);
            $line = $o->lines->firstWhere('delivered_qty', '>=', 3);
            $this->sales->processReturn($o, $line->id, 2, 1, 'Near expiry, returned by shop');
        }

        // Damage write-off in the warehouse, and a correction (undo) of a mistaken receipt.
        $this->at(self::DAYS - 6, 16);
        $this->as($this->inventory);
        $this->stock->move($products[8]->id, $wm, Location::system('damaged'), 12, 'damage_adjustment', 'demo-adj-1', null, 'Bottles broken during unloading');
        $wrong = $this->stock->move($products[1]->id, Location::system('external'), $wp, 500, 'receipt', 'demo-wrong-1', null, 'GRN 4471 (entered twice by mistake)');
        $this->as($this->admin);
        $this->stock->reverse($wrong, 'Duplicate entry of GRN 4471');
        $this->as($this->inventory);
        $this->stock->move($products[1]->id, Location::system('external'), $wp, 400, 'receipt', 'demo-grn-4472', null, 'GRN 4472 from supplier');

        // Transfers waiting at every stage so each role has something to do today.
        $line = fn (array $skus) => collect($products)->filter(fn ($p) => in_array($p->sku, $skus, true))->mapWithKeys(fn ($p) => [$p->id => 100])->all();
        $this->send($wm, $locs[1], $line(['PARA500', 'ORS21', 'VITC500']), 'Weekly supply · Vehicle MH-12-XY-4410', self::DAYS - 1, null); // in transit
        $this->at(self::DAYS, 9);
        $this->as($this->inventory);
        $t = $this->tr->create($wm, $locs[0], $line(['AMOX500', 'CET10', 'MET500']), 'Weekly supply');
        $this->as($this->admin);
        $this->tr->approve($t); // approved, waiting to be dispatched
        $this->as($this->inventory);
        $this->tr->create($wm, $locs[3], $line(['PAN40', 'COUGH100']), 'Requested by Krishna Health'); // draft, needs approval
    }
}
