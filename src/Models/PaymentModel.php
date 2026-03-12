<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string $gateway_name
 * @property string $profile
 * @property PaymentStatus $status
 * @property string $amount
 * @property string $currency
 * @property string|null $gateway_reference
 * @property string|null $idempotency_key
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property array<string, mixed>|null $canonical_payload
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentModel extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'canonical_payload' => 'array',
            'metadata' => 'array',
            'status' => PaymentStatus::class,
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.payments', 'payments');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'payment_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class, 'payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class, 'payment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::Completed);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::Failed);
    }

    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway_name', $gateway);
    }

    public function scopeForTenant($query, ?string $tenantId) {}
}
