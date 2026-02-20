<?php

namespace Frolax\Payment\Discovery;

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\GatewayRegistry;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

/**
 * Base service provider for gateway addon packages.
 *
 * Addon packages extend this class and implement gatewayAddon()
 * to enable auto-discovery when the package is installed.
 */
abstract class GatewayAddonServiceProvider extends ServiceProvider
{
    /**
     * Return the gateway addon contract instance.
     */
    abstract public function gatewayAddon(): GatewayAddonContract;

    public function register(): void
    {
        // Addon packages can override this to register bindings
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->app->afterResolving(GatewayRegistry::class, function (GatewayRegistry $registry) {
            $registry->registerAddon($this->gatewayAddon());
        });

        // If the registry is already resolved, register immediately
        if ($this->app->resolved(GatewayRegistry::class)) {
            $registry = $this->app->make(GatewayRegistry::class);
            $registry->registerAddon($this->gatewayAddon());
        }
    }
}
