<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
