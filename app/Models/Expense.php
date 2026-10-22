<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** submitted -> approved | rejected. Approved/rejected expenses are immutable. */
class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['expense_date' => 'date', 'reviewed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function distributor() { return $this->belongsTo(Distributor::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
