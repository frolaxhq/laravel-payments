<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $subscription_id
 * @property string|null $subscription_item_id
 * @property string $feature
 * @property string $quantity
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $recorded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SubscriptionUsage extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.subscription_usage', 'payment_subscription_usage');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class);
    }
}
