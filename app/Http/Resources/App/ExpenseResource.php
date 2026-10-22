<?php

namespace App\Http\Resources\App;

class ExpenseResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'category' => $this->category, 'amount' => self::money($this->amount),
            'expense_date' => $this->expense_date->toDateString(), 'description' => $this->description,
            'has_receipt' => (bool) $this->receipt_path,
            'status' => $this->status, 'review_note' => $this->review_note, 'reviewed_at' => self::iso($this->reviewed_at),
            'submitted_at' => self::iso($this->created_at),
        ];
    }
}
