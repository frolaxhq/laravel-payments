<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\Models\TaxRate;

class TaxCalculator
{
    /**
     * Calculate tax for a given amount and region.
     */
    public function calculate(float $amount, ?string $region = null): array
    {
        $taxRates = TaxRate::active()
            ->when($region, fn ($q) => $q->forRegion($region))
            ->get();

        if ($taxRates->isEmpty()) {
            return [
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'rates' => [],
            ];
        }

        $totalTax = 0;
        $appliedRates = [];

        foreach ($taxRates as $taxRate) {
            $taxAmount = $taxRate->calculate($amount);
            $totalTax += $taxAmount;
            $appliedRates[] = [
                'name' => $taxRate->name,
                'rate' => $taxRate->rate,
                'amount' => $taxAmount,
                'inclusive' => $taxRate->is_inclusive,
            ];
        }

        return [
            'subtotal' => $amount,
            'tax' => round($totalTax, 2),
            'total' => round($amount + $totalTax, 2),
            'rates' => $appliedRates,
        ];
    }
}
