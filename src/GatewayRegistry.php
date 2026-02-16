<?php

namespace Frolax\Payment;

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Exceptions\GatewayNotFoundException;

class GatewayRegistry
{
    /**
     * @var array<string, array{driver: class-string|callable, addon: ?GatewayAddonContract, display_name: string, capabilities: string[]}>
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
        if (!$this->has($key)) {
            throw new GatewayNotFoundException("Gateway [{$key}] is not registered.");
        }

        $entry = $this->gateways[$key];
        $driver = $entry['driver'];

        if (is_callable($driver) && !is_string($driver)) {
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
     * @return array<string, array{driver: class-string|callable, display_name: string, capabilities: string[], addon: ?GatewayAddonContract}>
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
     * Get the credential schema for a gateway (from addon or empty array).
     */
    public function credentialSchema(string $key): array
    {
        $addon = $this->addon($key);

        return $addon?->credentialSchema() ?? [];
    }
}
