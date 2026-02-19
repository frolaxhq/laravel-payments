<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.coupons', 'payment_coupons');
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // ── Validation ──

    public function isValid(?float $orderAmount = null, ?string $customerEmail = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }
        if ($orderAmount !== null && $this->min_spend && $orderAmount < $this->min_spend) {
            return false;
        }

        if ($customerEmail && $this->max_uses_per_customer) {
            $customerUses = $this->usages()->where('customer_email', $customerEmail)->count();
            if ($customerUses >= $this->max_uses_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount amount.
     */
    public function calculate(float $amount): float
    {
        return match ($this->type) {
            'percent' => round($amount * ($this->value / 100), 2),
            'fixed' => min($this->value, $amount),
            default => 0,
        };
    }

    /**
     * Record usage.
     */
    public function recordUsage(string $paymentId, float $discountAmount, ?string $customerEmail = null): void
    {
        $this->usages()->create([
            'payment_id' => $paymentId,
            'customer_email' => $customerEmail,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);

        $this->increment('used_count');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
