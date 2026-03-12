<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $payment_id
 * @property int $attempt_no
 * @property AttemptStatus $status
 * @property string|null $gateway_reference
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $errors
 * @property string|null $duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentAttempt extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'errors' => 'array',
            'duration_ms' => 'decimal:2',
            'status' => AttemptStatus::class,
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.attempts', 'payment_attempts');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }
}
