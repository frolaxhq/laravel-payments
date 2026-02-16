<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.credit_notes', 'payment_credit_notes');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
