<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.payouts', 'payment_payouts');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PayoutRecipient::class, 'recipient_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
