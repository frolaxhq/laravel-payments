<?php

namespace Frolax\Payment\Contracts;

/**
 * Contract for gateway addon packages to implement.
 *
 * Addon packages implement this contract to describe their gateway
 * to the core package's auto-discovery system.
 */
interface GatewayAddonContract
{
    /**
     * The unique gateway key (e.g. "bkash", "stripe").
     */
    public function gatewayKey(): string;

    /**
     * Human-friendly display name (e.g. "bKash", "Stripe").
     */
    public function displayName(): string;

    /**
     * The fully-qualified driver class or a factory callable.
     *
     * @return class-string<GatewayDriverContract>|callable
     */
    public function driverClass(): string|callable;

    /**
     * List of capability interface class names this gateway supports.
     *
     * Example: [SupportsRecurring::class, SupportsRefund::class]
     *
     * @return array<class-string>
     */
    public function capabilities(): array;

    /**
     * Credential schema: which keys are required/optional.
     *
     * Example: ['key' => 'required', 'secret' => 'required', 'webhook_secret' => 'optional']
     *
     * @return array<string, string>
     */
    public function credentialSchema(): array;

    /**
     * Default config keys/values that should be merged.
     */
    public function defaultConfig(): array;
}
