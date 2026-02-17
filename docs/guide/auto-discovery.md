# Auto-Discovery

Gateway addon packages are automatically discovered and registered when installed via Composer. No manual registration, no config file changes.

## How It Works

```
1. User installs addon:  composer require frolax/payment-bkash
2. Laravel auto-discovers the addon's ServiceProvider
3. ServiceProvider extends GatewayAddonServiceProvider
4. On boot, the addon registers itself into GatewayRegistry
5. Gateway is available: Payment::gateway('bkash')->create(...)
```

## The Discovery Chain

### Laravel Package Auto-Discovery

The addon's `composer.json` declares a service provider:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Frolax\\PaymentBkash\\BkashServiceProvider"
            ]
        }
    }
}
```

Laravel automatically discovers and registers this provider on `composer install/update`.

### GatewayAddonServiceProvider

The addon's service provider extends the base `GatewayAddonServiceProvider`:

```php
class BkashServiceProvider extends GatewayAddonServiceProvider
{
    public function gatewayAddon(): GatewayAddonContract
    {
        return new BkashGatewayAddon();
    }
}
```

On boot, `GatewayAddonServiceProvider` calls `$registry->registerAddon($addon)`:

```php
// Internal implementation
public function boot(): void
{
    $this->app->afterResolving(GatewayRegistry::class, function (GatewayRegistry $registry) {
        $registry->registerAddon($this->gatewayAddon());
    });
}
```

### GatewayRegistry

The registry stores the addon's metadata and resolves the driver when `Payment::gateway('bkash')` is called:

```php
// User code — just works after composer require
$result = Payment::gateway('bkash')->create($data);
```

## Listing Discovered Gateways

```bash
php artisan payments:gateways
```

Output:

```
+--------+--------------+--------------+------------------+---------------+-------+
| Key    | Display Name | Driver       | Capabilities     | Config Source  | Type  |
+--------+--------------+--------------+------------------+---------------+-------+
| stripe | Stripe       | StripeDriver | redirect,webhook | env           | core  |
| bkash  | bKash        | BkashDriver  | redirect,webhook | env           | addon |
+--------+--------------+--------------+------------------+---------------+-------+
```

The **Type** column shows whether a gateway was registered as `core` (via `$registry->register()`) or `addon` (via `GatewayAddonContract`).

## Discovery vs. Manual Registration

| Approach | Use When |
|----------|----------|
| **Auto-Discovery** (addon package) | Distributing a gateway driver as a Composer package |
| **Manual Registration** (service provider) | App-specific gateways that live inside your app |

Both approaches result in the same `GatewayRegistry` entry. The difference is only in how the driver gets registered.
