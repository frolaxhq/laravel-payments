<?php

namespace Frolax\Payment;

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Exceptions\GatewayNotFoundException;

class GatewayRegistry
{
    /**
     * @var array<string, array{driver: class-string|callable, addon: ?GatewayAddonContract, display_name: string, capabilities: array<class-string>}>
     */
    protected array $gateways = [];

    /**
     * Register a gateway driver.
     */
    public function register(
        string $key,
        string|callable $driver,
        ?string $displayName = null,
        array $capabilities = [],
        ?GatewayAddonContract $addon = null,
    ): void {
        $this->gateways[$key] = [
            'driver' => $driver,
            'display_name' => $displayName ?? $key,
            'capabilities' => $capabilities,
            'addon' => $addon,
        ];
    }

    /**
     * Register from a GatewayAddonContract.
     */
    public function registerAddon(GatewayAddonContract $addon): void
    {
        $this->register(
            key: $addon->gatewayKey(),
            driver: $addon->driverClass(),
            displayName: $addon->displayName(),
            capabilities: $addon->capabilities(),
            addon: $addon,
        );
    }

    /**
     * Resolve a gateway driver instance.
     */
    public function resolve(string $key): GatewayDriverContract
    {
        if (! $this->has($key)) {
            throw new GatewayNotFoundException("Gateway [{$key}] is not registered.");
        }

        $entry = $this->gateways[$key];
        $driver = $entry['driver'];

        if (is_callable($driver) && ! is_string($driver)) {
            return $driver();
        }

        return app($driver);
    }

    /**
     * Check if a gateway is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    /**
     * Get all registered gateway keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * Get all registered gateways with metadata.
     *
     * @return array<string, array{driver: class-string|callable, display_name: string, capabilities: array<class-string>, addon: ?GatewayAddonContract}>
     */
    public function all(): array
    {
        return $this->gateways;
    }

    /**
     * Get the addon contract for a gateway, if registered via addon.
     */
    public function addon(string $key): ?GatewayAddonContract
    {
        return $this->gateways[$key]['addon'] ?? null;
    }

    /**
     * Get the registered capabilities for a gateway.
     *
     * @return array<class-string>
     */
    public function capabilities(string $key): array
    {
        return $this->gateways[$key]['capabilities'] ?? [];
    }

    /**
     * Check if a gateway has a specific capability registered.
     *
     * @param  class-string  $capability
     */
    public function hasCapability(string $key, string $capability): bool
    {
        return in_array($capability, $this->capabilities($key), true);
    }

    /**
     * Get all gateways that support a given capability.
     *
     * @param  class-string  $capability
     * @return array<string, array{driver: class-string|callable, display_name: string, capabilities: array<class-string>, addon: ?GatewayAddonContract}>
     */
    public function supporting(string $capability): array
    {
        return array_filter($this->gateways, function (array $entry) use ($capability) {
            return in_array($capability, $entry['capabilities'], true);
        });
    }

    /**
     * Derive capabilities by reflecting on which Supports* interfaces the driver implements.
     *
     * @return array<class-string>
     */
    public function resolvedCapabilities(string $key): array
    {
        $driver = $this->resolve($key);

        return array_values(array_filter(
            class_implements($driver) ?: [],
            fn (string $interface) => str_starts_with($interface, 'Frolax\\Payment\\Contracts\\Supports'),
        ));
    }

    /**
     * Get the credential schema for a gateway (from addon or empty array).
     */
    public function credentialSchema(string $key): array
    {
        $addon = $this->addon($key);

        return $addon?->credentialSchema() ?? [];
    }
}
