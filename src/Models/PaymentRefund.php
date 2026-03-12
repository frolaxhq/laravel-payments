<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $payment_id
 * @property string|null $refund_reference
 * @property string $amount
 * @property string $currency
 * @property RefundStatus $status
 * @property string|null $reason
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $gateway_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
