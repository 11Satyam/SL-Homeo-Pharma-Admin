<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Staff expense category. `code` is what expenses store and never changes; `name` is the label. "other" always needs a description. */
class ExpenseCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'receipt_required' => 'boolean', 'max_amount' => 'decimal:2'];

    public function scopeOrdered($q) { return $q->orderBy('sort')->orderBy('name'); }

    /** Categories staff can choose now. */
    public static function choosable(): Collection
    {
        return static::where('active', true)->ordered()->get();
    }

    /** code => label for every category (inactive too), for showing past expenses. */
    public static function labels(): array
    {
        return static::pluck('name', 'code')->all();
    }

    /** What the field app needs to build its expense form. */
    public function forApp(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'receipt_required' => $this->receipt_required,
            'description_required' => $this->code === 'other', 'max_amount' => $this->max_amount];
    }
}
