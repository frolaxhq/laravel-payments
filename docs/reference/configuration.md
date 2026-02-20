# Configuration Reference

The full `config/payments.php` file with all available options.

## Default Gateway

```php
'default' => env('PAYMENT_GATEWAY', 'stripe'),
```

The gateway used when `Payment::create()` is called without specifying a gateway.

## Profile

```php
'profile' => env('PAYMENT_PROFILE', 'test'),
```

Default credential profile. Use `test` for sandbox/development and `live` for production.

## Gateway Definitions

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

Each gateway has credential sets keyed by profile (`test`, `live`, or custom profiles).

## Credential Storage

```php
'credential_storage' => env('PAYMENT_CREDENTIAL_STORAGE', 'env'),
```

| Value | Description |
|-------|-------------|
| `env` | Read from `config/payments.php` only |
| `database` | Read from `payment_gateway_credentials` table |
| `composite` | Database first, fallback to ENV |

## Routes

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'payments',
    'middleware' => ['web'],
    'webhook_middleware' => [],
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Register built-in routes |
| `prefix` | `payments` | URL prefix for all payment routes |
| `middleware` | `['web']` | Middleware for return/cancel routes |
| `webhook_middleware` | `[]` | Middleware for webhook route (no CSRF) |

## Logging

```php
'logging' => [
    'level' => env('PAYMENT_LOG_LEVEL', 'basic'),
    'channel' => env('PAYMENT_LOG_CHANNEL', null),
    'db_logging' => false,
    'redacted_keys' => [
        'secret', 'password', 'token', 'key',
        'card_number', 'cvv', 'cvc', 'pin',
        'authorization', 'signature',
    ],
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `level` | `basic` | Log verbosity: `off`, `errors_only`, `basic`, `verbose`, `debug` |
| `channel` | `null` | Laravel log channel (null = default) |
| `db_logging` | `false` | Store logs in `payment_logs` table |
| `redacted_keys` | `[...]` | Keys to mask as `[REDACTED]` |

## Persistence

```php
'persistence' => [
    'enabled' => true,
    'payments' => true,
    'attempts' => true,
    'webhooks' => true,
    'refunds' => true,
    'logs' => true,
],
```

Toggle database persistence for each record type independently.

## Idempotency

```php
'idempotency' => [
    'auto_generate' => true,
    'ttl_hours' => 24,
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `auto_generate` | `true` | Auto-generate idempotency keys from payload |
| `ttl_hours` | `24` | How long idempotency keys remain active |

## Table Names

```php
'tables' => [
    'gateways' => 'payment_gateways',
    'credentials' => 'payment_gateway_credentials',
    'payments' => 'payments',
    'attempts' => 'payment_attempts',
    'webhooks' => 'payment_webhook_events',
    'refunds' => 'payment_refunds',
    'logs' => 'payment_logs',
],
```

Customize table names if they conflict with your application.
