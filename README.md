# Laravel Payments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/frolaxhq/laravel-payments.svg?style=flat-square)](https://packagist.org/packages/frolaxhq/laravel-payments)
[![Tests](https://img.shields.io/github/actions/workflow/status/frolaxhq/laravel-payments/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/frolaxhq/laravel-payments/actions?query=workflow%3Arun-tests+branch%3Amain)

A production-grade, gateway-agnostic payment abstraction for Laravel. One canonical payload for every gateway—forever.

## Features

- **One Canonical Payload** — Same shape for every gateway, always
- **Capability-Based Drivers** — Open/Closed: core never branches on gateway name
- **Auto-Discovery** — Install a gateway addon package, it works immediately
- **Multi-Tenant Credentials** — ENV, database, or composite with priority & time windows
- **Universal Webhooks** — Idempotent, replay-safe webhook/return/cancel endpoints
- **Structured Logging** — Dot-notation, redacted, to DB + Laravel channels
- **Full CLI** — Generate gateways, list drivers, sync credentials, replay webhooks

## Installation

```bash
composer require frolaxhq/laravel-payments
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag="payments-config"
php artisan vendor:publish --tag="payments-migrations"
php artisan migrate
```

## Quick Start

```php
use Frolax\Payment\Facades\Payment;

// Create a payment
$result = Payment::gateway('stripe')->create([
    'idempotency_key' => 'order-123-attempt-1',
    'order' => [
        'id' => 'ORD-123',
        'description' => 'Premium Plan',
        'items' => [
            ['name' => 'Premium Plan', 'quantity' => 1, 'unit_price' => 2999],
        ],
    ],
    'money' => ['amount' => 2999, 'currency' => 'USD'],
    'customer' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
    ],
    'urls' => [
        'return' => 'https://myapp.com/payments/return/stripe',
        'cancel' => 'https://myapp.com/payments/cancel/stripe',
        'webhook' => 'https://myapp.com/payments/webhook/stripe',
    ],
    'metadata' => ['plan' => 'premium'],
]);

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

// Verify from callback
$result = Payment::gateway('stripe')->verifyFromRequest($request);

// Refund (if supported)
$result = Payment::gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'money' => ['amount' => 1000, 'currency' => 'USD'],
    'reason' => 'Customer request',
]);

// Query status (if supported)
$result = Payment::gateway('stripe')->status([
    'payment_id' => 'PAY-001',
]);
```

## Canonical Payload Shape

Every gateway driver receives the same payload structure:

| Key | Type | Required |
|-----|------|----------|
| `idempotency_key` | string | Auto-generated if not provided |
| `order.id` | string | ✓ |
| `order.description` | string | |
| `order.items[]` | array | |
| `money.amount` | number | ✓ |
| `money.currency` | string | ✓ |
| `customer.name` | string | |
| `customer.email` | string | |
| `customer.phone` | string | |
| `customer.address.*` | object | |
| `urls.return` | string | |
| `urls.cancel` | string | |
| `urls.webhook` | string | |
| `context.ip` | string | |
| `context.user_agent` | string | |
| `context.locale` | string | |
| `metadata` | object | Freeform |
| `extra` | object | Driver-only overrides |

## Runtime Selectors

```php
// Multi-tenant
Payment::gateway('stripe')
    ->usingContext(['tenant_id' => 'tenant-abc'])
    ->create($payload);

// Switch profile
Payment::gateway('stripe')
    ->withProfile('live')
    ->create($payload);

// One-off credentials
Payment::gateway('stripe')
    ->usingCredentials(['key' => 'sk_live_...', 'secret' => '...'])
    ->create($payload);
```

## Driver System

Drivers implement `GatewayDriverContract` plus optional capability interfaces:

| Interface | Capability |
|-----------|-----------|
| `SupportsHostedRedirect` | Redirect to hosted checkout |
| `SupportsWebhookVerification` | Webhook signature verification |
| `SupportsRefund` | Refund processing |
| `SupportsStatusQuery` | Payment status queries |
| `SupportsTokenization` | Saved payment methods (future) |
| `SupportsInstallments` | EMI/installment plans (future) |

## Creating a Gateway

### Generate inline (in your app):

```bash
php artisan payments:make-gateway Bkash \
    --key=bkash \
    --display="bKash" \
    --capabilities=redirect,webhook,refund \
    --credentials=key:required,secret:required,webhook_secret:optional
```

### Generate as addon package:

```bash
php artisan payments:make-gateway Bkash --addon \
    --key=bkash \
    --display="bKash" \
    --capabilities=redirect,webhook,refund
```

Addon packages auto-register via Laravel package auto-discovery. Zero manual registration.

## Credential Configuration

### ENV-based (config/payments.php):

```php
'gateways' => [
    'stripe' => [
        'test' => [
            'key' => env('STRIPE_TEST_KEY'),
            'secret' => env('STRIPE_TEST_SECRET'),
            'webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        ],
        'live' => [
            'key' => env('STRIPE_LIVE_KEY'),
            'secret' => env('STRIPE_LIVE_SECRET'),
            'webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        ],
    ],
],
```

### Database-based:

Set `PAYMENT_CREDENTIAL_STORAGE=database` or `composite` (DB first, fallback to ENV).

The `payment_gateway_credentials` table supports:
- Tenant isolation (`tenant_id`)
- Profiles (`test`/`live`)
- Time windows (`effective_from`/`effective_to`)
- Priority-based rotation
- Encrypted credential storage

## Webhook Endpoints

Default routes (configurable prefix/middleware):

| Method | Path | Description |
|--------|------|-------------|
| POST | `/payments/webhook/{gateway}` | Receive gateway webhooks |
| GET | `/payments/return/{gateway}` | Payment return callback |
| GET | `/payments/cancel/{gateway}` | Payment cancel callback |

Webhooks are idempotent and replay-safe.

## CLI Commands

```bash
# List all discovered gateways
php artisan payments:gateways

# Generate a gateway driver skeleton
php artisan payments:make-gateway {name} [--addon] [--capabilities=...] [--credentials=...]

# Validate credentials exist
php artisan payments:credentials:sync [--gateway=...] [--profile=...] [--tenant=...]

# Replay a stored webhook event
php artisan payments:webhooks:replay {webhook_event_id}
```

## Events

| Event | When |
|-------|------|
| `PaymentCreated` | Payment successfully created |
| `PaymentVerified` | Payment verified from callback |
| `PaymentFailed` | Payment creation failed |
| `PaymentCancelled` | Payment cancelled by user |
| `PaymentRefundRequested` | Refund initiated |
| `PaymentRefunded` | Refund completed |
| `WebhookReceived` | Webhook received from gateway |

## Database Tables

All tables use ULID primary keys:

- `payment_gateways` — Gateway definitions
- `payment_gateway_credentials` — Encrypted credentials with tenant/profile/time windows
- `payments` — Canonical payment records
- `payment_attempts` — Per-create attempt history
- `payment_webhook_events` — Raw webhook storage (replay-safe)
- `payment_refunds` — Refund lifecycle
- `payment_logs` — Structured dot-notation logs

## Logging

Configurable verbosity: `off | errors_only | basic | verbose | debug`

Logs are automatically redacted (secrets, tokens, card data) and written to both Laravel log channels and the `payment_logs` database table with dot-notation keys.

## For Gateway Addon Authors

1. Create a package that extends `GatewayAddonServiceProvider`
2. Implement `GatewayAddonContract`
3. Implement `GatewayDriverContract` + capability interfaces
4. Add Laravel auto-discovery to `composer.json`
5. Users install with `composer require` — gateway works immediately

Use `php artisan payments:make-gateway {name} --addon` to generate the full scaffold.

## Testing

```bash
composer test
```

Tests use Orchestra Testbench with in-memory SQLite.

## License

MIT License. See [LICENSE.md](LICENSE.md).
