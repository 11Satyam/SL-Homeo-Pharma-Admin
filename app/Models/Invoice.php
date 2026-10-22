<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['issue_date' => 'date', 'due_date' => 'date'];
    public function shop() { return $this->belongsTo(Shop::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function creditNotes() { return $this->hasMany(CreditNote::class); }

    /** outstanding() as SQL, for filtering and summing lists without a query per invoice. */
    public const OUTSTANDING_SQL = '(invoices.total'
        .' - COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.invoice_id = invoices.id), 0)'
        .' - COALESCE((SELECT SUM(cn.amount) FROM credit_notes cn WHERE cn.invoice_id = invoices.id), 0))';

    public function scopeUnpaid($q)
    {
        return $q->whereRaw(self::OUTSTANDING_SQL.' > 0.004');
    }

    public function scopeOverdue($q)
    {
        return $q->unpaid()->whereDate('due_date', '<', today());
    }

    public static function outstandingTotal($q): string
    {
        return number_format((float) (clone $q)->unpaid()->sum(\Illuminate\Support\Facades\DB::raw(self::OUTSTANDING_SQL)), 2, '.', '');
    }

    /** Outstanding = total - allocated payments - applied credits. */
    public function outstanding(): string
    {
        $paid = $this->allocations()->sum('amount');
        $credit = $this->creditNotes()->sum('amount');
        return bcsub(bcsub((string) $this->total, (string) $paid, 2), (string) $credit, 2);
    }
}
