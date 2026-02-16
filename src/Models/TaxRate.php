<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('payments.tables.tax_rates', 'payment_tax_rates');
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    public function calculate(float $amount): float
    {
        if ($this->is_inclusive) {
            return round($amount - ($amount / (1 + $this->rate / 100)), 2);
        }

        return round($amount * $this->rate / 100, 2);
    }
}
