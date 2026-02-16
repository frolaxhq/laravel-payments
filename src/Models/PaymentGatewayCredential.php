<?php

namespace Frolax\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayCredential extends Model
{
    use HasUlids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'priority' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('payments.tables.credentials', 'payment_gateway_credentials');
    }
}
