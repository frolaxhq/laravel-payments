# Creating Addon Packages

Package your gateway driver as a standalone Composer package that auto-registers when installed. Users just `composer require` and the gateway is available.

## Generate an Addon Scaffold

```bash
php artisan payments:make-gateway Bkash --addon \
    --key=bkash \
    --display="bKash" \
    --capabilities=redirect,webhook,refund \
    --credentials=app_key:required,app_secret:required
```

This generates a complete package at `packages/frolax/payment-bkash/`:

```
packages/frolax/payment-bkash/
├── composer.json
├── config/
│   └── payment-bkash.php
├── src/
│   ├── BkashDriver.php
│   ├── BkashGatewayAddon.php
│   └── BkashServiceProvider.php
├── tests/
│   └── BkashDriverTest.php
└── docs/
    └── README.md
```

## Package Structure

### composer.json

```json
{
  "name": "frolax/payment-bkash",
  "description": "bKash payment gateway driver for frolax/laravel-payments",
  "require": {
    "php": "^8.4",
    "frolaxhq/laravel-payments": "^1.0"
  },
  "autoload": {
    "psr-4": {
      "Frolax\\PaymentBkash\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "Frolax\\PaymentBkash\\BkashServiceProvider"
      ]
    }
  }
}
```

### GatewayAddonContract

Your addon implements `GatewayAddonContract` to declare its metadata:

```php
<?php

namespace Frolax\PaymentBkash;

use Frolax\Payment\Contracts\GatewayAddonContract;

class BkashGatewayAddon implements GatewayAddonContract
{
    public function gatewayKey(): string
    {
        return 'bkash';
    }

    public function displayName(): string
    {
        return 'bKash';
    }

    public function driverClass(): string|callable
    {
        return BkashDriver::class;
    }

    public function capabilities(): array
    {
        return ['redirect', 'webhook', 'refund'];
    }

    public function credentialSchema(): array
    {
        return [
            'app_key' => 'required',
            'app_secret' => 'required',
            'username' => 'required',
            'password' => 'required',
            'webhook_secret' => 'optional',
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'sandbox_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'live_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
        ];
    }
}
```

### Service Provider

Extend `GatewayAddonServiceProvider`:

```php
<?php

namespace Frolax\PaymentBkash;

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\Discovery\GatewayAddonServiceProvider;

class BkashServiceProvider extends GatewayAddonServiceProvider
{
    public function gatewayAddon(): GatewayAddonContract
    {
        return new BkashGatewayAddon();
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-bkash.php',
            'payments.gateways.bkash',
        );
    }
}
```

### Driver Implementation

Same as [Creating Gateway Drivers](/guide/creating-drivers), implement `GatewayDriverContract` + capability interfaces.

## Publishing Your Addon

### 1. Development (Local)

Add the package as a local repository in your app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/frolax/payment-bkash"
        }
    ]
}
```

Install:

```bash
composer require frolax/payment-bkash:@dev
```

### 2. Production (Packagist)

1. Push your addon to GitHub (e.g., `frolaxhq/payment-bkash`)
2. Register on [Packagist](https://packagist.org)
3. Users install with:

```bash
composer require frolax/payment-bkash
```

The gateway is available immediately with zero config.

## Checklist

Before publishing your addon:

- [ ] `GatewayAddonContract` implemented with all metadata
- [ ] `GatewayDriverContract` implemented with `create()` and `verify()`
- [ ] Capability interfaces implemented for listed capabilities
- [ ] `credentialSchema()` correctly lists required/optional keys
- [ ] Laravel auto-discovery added to `composer.json`
- [ ] Tests covering create, verify, and webhook verification
- [ ] Config file with default settings
- [ ] README with setup instructions

## Credential Validation

Your credential schema is used by `payments:credentials:sync` to validate that required credentials are present:

```bash
# After installing the addon, users can validate:
php artisan payments:credentials:sync --gateway=bkash --profile=live
```
