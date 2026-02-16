<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSplit extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.payment_splits', 'payment_splits');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PayoutRecipient::class, 'recipient_id');
    }
}
