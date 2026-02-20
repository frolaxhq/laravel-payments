<?php

namespace Frolax\Payment\Models;

use App\Models\Plan;
use Frolax\Payment\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.subscriptions', 'payment_subscriptions');
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'quantity' => 'integer',
            'metadata' => 'array',
            'gateway_data' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at?->isFuture();
    }

    public function onGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled
            && $this->ends_at?->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }

    public function isPaused(): bool
    {
        return $this->status === SubscriptionStatus::Paused;
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled;
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
        ]);
    }

    #[Scope]
    protected function forGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway_name', $gateway);
    }

    #[Scope]
    public function forTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    #[Scope]
    public function forPlan(Builder $query, string $planId): Builder
    {
        return $query->where('plan_id', $planId);
    }

    #[Scope]
    public function expiring(Builder $query, int $withinDays = 7): Builder
    {
        return $query->where('current_period_end', '<=', now()->addDays($withinDays))
            ->where('status', SubscriptionStatus::Active);
    }
}
