<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopeForTenant($query, ?string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
