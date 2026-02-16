<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.webhooks', 'payment_webhook_events');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway_name', $gateway);
    }

    public function markProcessed(): void
    {
        $this->update([
            'processed' => true,
            'processed_at' => now(),
        ]);
    }
}
