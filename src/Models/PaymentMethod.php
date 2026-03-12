<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gateway_name
 * @property string|null $customer_id
 * @property string $gateway_method_id
 * @property string $type
 * @property array<string, mixed>|null $details
 * @property bool $is_default
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array<string, mixed>|null $metadata
 */
class PaymentMethod extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.methods', 'payment_methods');
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    // ── Scopes ──

    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway_name', $gateway);
    }

    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // ── Helpers ──

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function makeDefault(): void
    {
        static::where('customer_id', $this->customer_id)
            ->where('gateway_name', $this->gateway_name)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
