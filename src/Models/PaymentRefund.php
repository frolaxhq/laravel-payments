<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'metadata' => 'array',
            'status' => RefundStatus::class,
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.refunds', 'payment_refunds');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }
}
