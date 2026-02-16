<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutRecipient extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.payout_recipients', 'payment_payout_recipients');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'recipient_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(PaymentSplit::class, 'recipient_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
