<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
