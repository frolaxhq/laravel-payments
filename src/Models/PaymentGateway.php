<?php

namespace Frolax\Payment\Models;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\GatewayRegistry;
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
            'is_active' => 'boolean',
            'metadata' => 'array',
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

    // -------------------------------------------------------
    // Gateway introspection helpers
    // -------------------------------------------------------

    /**
     * Check if this gateway supports a specific capability.
     *
     * @param  class-string  $capability  e.g. SupportsRecurring::class
     */
    public function supports(string $capability): bool
    {
        return $this->registry()->hasCapability($this->driver, $capability);
    }

    /**
     * Get the registered capabilities for this gateway.
     *
     * @return array<class-string>
     */
    public function capabilities(): array
    {
        return $this->registry()->capabilities($this->driver);
    }

    /**
     * Resolve the driver instance for this gateway.
     */
    public function resolveDriver(): GatewayDriverContract
    {
        return $this->registry()->resolve($this->driver);
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: only gateways that support a specific capability.
     *
     * @param  class-string  $capability
     */
    #[Scope]
    protected function supporting(Builder $query, string $capability): Builder
    {
        $registry = $this->registry();
        $supportingKeys = array_keys($registry->supporting($capability));

        return $query->whereIn('driver', $supportingKeys);
    }

    // -------------------------------------------------------
    // Internal
    // -------------------------------------------------------

    protected function registry(): GatewayRegistry
    {
        return app(GatewayRegistry::class);
    }
}
