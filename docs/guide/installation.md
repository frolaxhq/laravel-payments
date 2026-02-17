# Installation

## Requirements

- PHP 8.4+
- Laravel 11+

## Install via Composer

```bash
composer require frolaxhq/laravel-payments
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="payments-config"
```

This publishes `config/payments.php` with all default settings.

## Publish Migrations

```bash
php artisan vendor:publish --tag="payments-migrations"
```

This publishes 7 migration files:

| Migration | Table | Purpose |
|-----------|-------|---------|
| `create_payment_gateways_table` | `payment_gateways` | Gateway definitions |
| `create_payment_gateway_credentials_table` | `payment_gateway_credentials` | Encrypted credentials |
| `create_payments_table` | `payments` | Payment records |
| `create_payment_attempts_table` | `payment_attempts` | Attempt history |
| `create_payment_webhook_events_table` | `payment_webhook_events` | Webhook storage |
| `create_payment_refunds_table` | `payment_refunds` | Refund records |
| `create_payment_logs_table` | `payment_logs` | Structured logs |

## Run Migrations

```bash
php artisan migrate
```

## Environment Configuration

Add the following to your `.env` file:

```dotenv
# Default gateway
PAYMENT_GATEWAY=stripe

# Profile (test or live)
PAYMENT_PROFILE=test

# Credential storage mode: env, database, or composite
PAYMENT_CREDENTIAL_STORAGE=env

# Logging level: off, errors_only, basic, verbose, debug
PAYMENT_LOG_LEVEL=basic

# Gateway-specific credentials (example for Stripe)
STRIPE_TEST_KEY=sk_test_...
STRIPE_TEST_SECRET=whsec_...
```

## Verify Installation

```bash
# List discovered gateways
php artisan payments:gateways

# Validate credentials
php artisan payments:credentials:sync
```

## Optional: Disable Features

You can selectively disable features in `config/payments.php`:

```php
// Disable database persistence entirely
'persistence' => [
    'enabled' => false,
],

// Disable routes (if you define your own)
'routes' => [
    'enabled' => false,
],

// Disable DB logging (keep channel logging)
'logging' => [
    'db_logging' => false,
],
```

## Next Steps

- [Quick Start](/guide/quick-start) — Build a payment flow in 5 minutes
- [Configuration Reference](/reference/configuration) — Full configuration options
