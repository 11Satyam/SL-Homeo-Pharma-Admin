<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Retry-safe creation keyed by (owner, client Idempotency-Key). A repeated key returns
 * the original record instead of posting again; a concurrent duplicate loses the
 * race on the unique index and also resolves to the original.
 */
final class Idempotency
{
    /** @return array{0: Model, 1: bool} [record, replayed] */
    public static function run(string $model, string $ownerColumn, int $ownerId, string $key, Closure $create): array
    {
        $find = fn () => $model::where($ownerColumn, $ownerId)->where('idempotency_key', $key)->first();
        if ($existing = $find()) {
            return [$existing, true];
        }
        try {
            return [$create(), false];
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $find()) {
                return [$existing, true];
            }
            throw $e;
        }
    }
}
