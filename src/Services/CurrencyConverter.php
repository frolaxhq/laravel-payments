<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\Models\ExchangeRate;

class CurrencyConverter
{
    /**
     * Convert an amount between currencies.
     */
    public function convert(float $amount, string $from, string $to): array
    {
        if (strtoupper($from) === strtoupper($to)) {
            return [
                'original_amount' => $amount,
                'converted_amount' => $amount,
                'from' => strtoupper($from),
                'to' => strtoupper($to),
                'rate' => 1.0,
                'source' => 'identity',
            ];
        }

        $rate = ExchangeRate::latest($from, $to);

        if (! $rate) {
            // Try reverse
            $reverseRate = ExchangeRate::latest($to, $from);
            if ($reverseRate && $reverseRate->rate > 0) {
                $convertedAmount = round($amount / $reverseRate->rate, 2);

                return [
                    'original_amount' => $amount,
                    'converted_amount' => $convertedAmount,
                    'from' => strtoupper($from),
                    'to' => strtoupper($to),
                    'rate' => round(1 / $reverseRate->rate, 8),
                    'source' => $reverseRate->source.' (inverted)',
                ];
            }

            throw new \RuntimeException("No exchange rate found for {$from} -> {$to}");
        }

        return [
            'original_amount' => $amount,
            'converted_amount' => $rate->convert($amount),
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => $rate->rate,
            'source' => $rate->source,
        ];
    }

    /**
     * Store a new exchange rate.
     */
    public function setRate(string $from, string $to, float $rate, string $source = 'manual'): ExchangeRate
    {
        return ExchangeRate::create([
            'from_currency' => strtoupper($from),
            'to_currency' => strtoupper($to),
            'rate' => $rate,
            'source' => $source,
            'fetched_at' => now(),
        ]);
    }
}
