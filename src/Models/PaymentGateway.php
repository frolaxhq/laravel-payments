<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'supports' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.gateways', 'payment_gateways');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(PaymentGatewayCredential::class, 'gateway_name', 'name');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'gateway_name', 'name');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
