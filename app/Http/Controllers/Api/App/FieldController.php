<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\BusinessDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Base for /api/app endpoints. Identity and distributor always come from the token's
 * user (EnsureFieldStaff guarantees both are active); client-supplied staff or
 * distributor IDs are never read.
 */
abstract class FieldController extends Controller
{
    protected function did(): int
    {
        return (int) auth()->user()->distributor_id;
    }

    protected function uid(): int
    {
        return (int) auth()->id();
    }

    /** Validation rule: an active shop belonging to the caller's distributor. */
    protected function ownShopRule()
    {
        return Rule::exists('shops', 'id')->where('distributor_id', $this->did())->where('status', 'active');
    }

    protected function ownShop(int $id): Shop
    {
        return Shop::where('distributor_id', $this->did())->findOrFail($id);
    }

    /** Required Idempotency-Key header for retryable posts (8-64 chars, [A-Za-z0-9_-]). */
    protected function idempotencyKey(Request $r): string
    {
        $key = (string) $r->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'An Idempotency-Key header (8-64 letters, digits, - or _) is required.']);
        }

        return $key;
    }

    /** 201 for a new record, 200 with Idempotent-Replayed: true when a retry returned the original. */
    protected function created(JsonResource $resource, bool $replayed): JsonResponse
    {
        return $resource->response()->setStatusCode($replayed ? 200 : 201)
            ->header('Idempotent-Replayed', $replayed ? 'true' : 'false');
    }

    /** Common list filters: per_page, from/to (business dates). */
    protected function listParams(Request $r, array $extra = []): array
    {
        return $r->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ] + $extra);
    }

    protected function applyDateRange($q, array $p, string $col = 'created_at')
    {
        return $q->when($p['from'] ?? null, fn ($q, $d) => $q->where($col, '>=', BusinessDay::range($d)[0]))
            ->when($p['to'] ?? null, fn ($q, $d) => $q->where($col, '<=', BusinessDay::range($d)[1]));
    }
}
