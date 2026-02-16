<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.exchange_rates', 'payment_exchange_rates');
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Convert an amount from this rate's source currency to target.
     */
    public function convert(float $amount): float
    {
        return round($amount * $this->rate, 2);
    }

    /**
     * Get the latest rate for a currency pair.
     */
    public static function latest(string $from, string $to): ?static
    {
        return static::where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to))
            ->orderByDesc('fetched_at')
            ->first();
    }

    public function scopeForPair($query, string $from, string $to)
    {
        return $query->where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to));
    }
}
