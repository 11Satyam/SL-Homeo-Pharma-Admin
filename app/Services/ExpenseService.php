<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Staff expenses: submitted -> approved | rejected. Auto-approval only applies when
 * fieldapp.expenses.auto_approve_upto is configured; reviewed expenses are immutable.
 */
class ExpenseService
{
    public function submit(User $u, array $d, ?UploadedFile $receipt = null): Expense
    {
        $disk = config('fieldapp.expenses.receipt_disk');
        $path = $receipt?->store('expense-receipts/'.$u->distributor_id, $disk);

        try {
            return DB::transaction(function () use ($u, $d, $path) {
                $amount = number_format((float) $d['amount'], 2, '.', '');
                $limit = config('fieldapp.expenses.auto_approve_upto');
                $auto = $limit !== null && bccomp($amount, (string) $limit, 2) <= 0;
                $e = Expense::create([
                    'user_id' => $u->id, 'distributor_id' => $u->distributor_id, 'category' => $d['category'],
                    'amount' => $amount, 'expense_date' => $d['expense_date'], 'description' => $d['description'] ?? null,
                    'receipt_path' => $path, 'idempotency_key' => $d['idempotency_key'] ?? null,
                    'status' => $auto ? 'approved' : 'submitted',
                    'reviewed_at' => $auto ? now() : null,
                    'review_note' => $auto ? "Auto-approved by policy (up to {$limit})." : null,
                ]);
                AuditLog::record('expense.submitted', $e, ['amount' => $amount, 'auto_approved' => $auto]);

                return $e;
            });
        } catch (\Throwable $ex) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
            throw $ex;
        }
    }

    public function review(Expense $e, string $decision, ?string $note = null): void
    {
        DB::transaction(function () use ($e, $decision, $note) {
            $e = Expense::lockForUpdate()->findOrFail($e->id);
            if ($e->status !== 'submitted') {
                throw new RuntimeException("Expense is already {$e->status}.");
            }
            if ($e->user_id === auth()->id()) {
                throw new RuntimeException('You cannot review your own expense.');
            }
            if ($decision === 'rejected' && ! $note) {
                throw new RuntimeException('A reason is required to reject an expense.');
            }
            $e->update(['status' => $decision, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => $note]);
            AuditLog::record('expense.'.$decision, $e, ['note' => $note]);
        });
    }
}
