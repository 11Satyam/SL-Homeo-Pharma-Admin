<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;
    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }

    public static function record(string $action, ?Model $entity = null, array $data = []): void
    {
        static::create([
            'user_id' => auth()->id(), 'action' => $action,
            'entity' => $entity ? class_basename($entity) : null, 'entity_id' => $entity?->getKey(),
            'data' => $data, 'ip' => request()?->ip(), 'created_at' => now(),
        ]);
    }
}
