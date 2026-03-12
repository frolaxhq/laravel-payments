<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $payment_id
 * @property string|null $attempt_id
 * @property string|null $gateway_name
 * @property string|null $profile
 * @property string $category
 * @property string $message
 * @property string $level
 * @property array<string, mixed>|null $context_flat
 * @property array<string, mixed>|null $context_nested
 * @property Carbon|null $occurred_at
 */
class PaymentLog extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context_flat' => 'array',
            'context_nested' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.logs', 'payment_logs');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway_name', $gateway);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
