# Credentials

Laravel Payments provides a flexible, production-grade credential management system that supports environment variables,
database storage, and a composite strategy combining both.

## Storage Modes

Set the mode in `config/payments.php` or via `.env`:

```dotenv
PAYMENT_CREDENTIAL_STORAGE=env       # Default
PAYMENT_CREDENTIAL_STORAGE=database
PAYMENT_CREDENTIAL_STORAGE=composite # DB first, fallback to ENV
```

## ENV-Based Credentials

The simplest approach—credentials live in `config/payments.php`:

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

Credentials are resolved by gateway key and profile:

```php
// Uses config('payments.gateways.stripe.test')
Payment::gateway('stripe')->withProfile('test')->create($data);
```

## Database-Based Credentials

For multi-tenant SaaS applications, store credentials in the `payment_gateway_credentials` table.

### Features

| Feature               | Description                                                               |
|-----------------------|---------------------------------------------------------------------------|
| **Tenant Isolation**  | Each tenant can have their own gateway credentials                        |
| **Time Windows**      | Credentials with `effective_from` / `effective_to` for scheduled rotation |
| **Priority Ordering** | Multiple credential sets resolved by priority (highest first)             |
| **Encryption**        | All credentials stored encrypted via Laravel's `EncryptedCast`            |
| **Profiles**          | Separate `test` and `live` credential sets                                |

### Storing Credentials

```php
use Frolax\Payment\Models\PaymentGatewayCredential;

PaymentGatewayCredential::create([
    'gateway_name' => 'stripe',
    'profile' => 'live',
    'tenant_id' => 'tenant-abc',
    'label' => 'Primary Stripe Account',
    'credentials' => [
        'key' => 'sk_live_...',
        'secret' => 'whsec_...',
    ],
    'is_active' => true,
    'priority' => 10,
    'effective_from' => now(),
    'effective_to' => null, // no expiry
]);
```

### Resolution Logic

When resolving credentials from the database, the repository:

1. Filters by `gateway_name` and `profile`
2. Filters by `tenant_id` (if provided in context)
3. Filters by `is_active = true`
4. Filters by time window (`effective_from <= now <= effective_to`)
5. Orders by `priority DESC`
6. Returns the first match

### Multi-Tenant Usage

```php
// The tenant_id is passed via context
Payment::gateway('stripe')
    ->usingContext(['tenant_id' => 'tenant-abc'])
    ->create($data);
```

### Credential Rotation

Use time windows for zero-downtime credential rotation:

```php
// Current credentials (expires end of month)
PaymentGatewayCredential::create([
    'gateway_name' => 'stripe',
    'profile' => 'live',
    'credentials' => ['key' => 'sk_live_OLD', ...],
    'effective_to' => '2024-12-31 23:59:59',
    'priority' => 10,
]);

// New credentials (starts next month)
PaymentGatewayCredential::create([
    'gateway_name' => 'stripe',
    'profile' => 'live',
    'credentials' => ['key' => 'sk_live_NEW', ...],
    'effective_from' => '2025-01-01 00:00:00',
    'priority' => 10,
]);
```

## Composite Credentials

The composite strategy checks the database first, then falls back to ENV:

```
DB (tenant-specific) → DB (global) → ENV config
```

This is ideal when:

- Most tenants use a shared gateway account (ENV)
- Some tenants have their own gateway accounts (DB)

## One-Off Credentials

Override credentials for a single operation:

```php
Payment::gateway('stripe')
    ->usingCredentials([
        'key' => 'sk_test_special',
        'secret' => 'whsec_special',
    ])
    ->create($data);
```

## Credentials

All resolved credentials are wrapped in a `Credentials`:

```php
$creds = $credentialsRepo->get('stripe', 'test');

$creds->gateway;              // 'stripe'
$creds->profile;              // 'test'
$creds->get('key');           // 'sk_test_...'
$creds->get('secret');        // 'whsec_...'
$creds->get('missing', 'default'); // 'default'
$creds->toSafeArray();        // Credentials masked as [REDACTED]
```

## Validating Credentials

```bash
# Check all gateways
php artisan payments:credentials:sync

# Check specific gateway and profile
php artisan payments:credentials:sync --gateway=stripe --profile=live

# Check for a specific tenant
php artisan payments:credentials:sync --gateway=stripe --tenant=tenant-abc
```
