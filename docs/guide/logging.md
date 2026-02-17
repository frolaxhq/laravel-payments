# Logging

Laravel Payments provides structured, dot-notation logging with automatic redaction of sensitive data and dual output to Laravel log channels and a queryable database table.

## Configuration

```php
// config/payments.php
'logging' => [
    'level' => env('PAYMENT_LOG_LEVEL', 'basic'),
    'channel' => env('PAYMENT_LOG_CHANNEL', null), // null = default channel
    'db_logging' => true,
    'redacted_keys' => [
        'secret', 'password', 'token', 'key',
        'card_number', 'cvv', 'cvc', 'pin',
        'authorization', 'signature',
    ],
],
```

## Log Levels

| Level | What's Logged |
|-------|--------------|
| `off` | Nothing |
| `errors_only` | Only errors |
| `basic` | Errors + warnings + key info messages |
| `verbose` | Everything except debug |
| `debug` | Everything including raw payloads |

## Automatic Logging

The payment system logs automatically at every stage:

| Category | Level | When |
|----------|-------|------|
| `payment.create` | info | Payment creation initiated |
| `payment.create.success` | info | Payment created successfully |
| `payment.create.failed` | error | Payment creation failed |
| `payment.verify` | info | Payment verification |
| `payment.refund` | info | Refund operation |
| `webhook.received` | info | Webhook received |
| `webhook.signature.invalid` | warning | Invalid webhook signature |
| `webhook.processed` | info | Webhook processed successfully |
| `webhook.idempotent` | info | Duplicate webhook ignored |
| `return.received` | info | Return callback received |
| `cancel.received` | info | Cancel callback received |

## Redaction

Sensitive keys are automatically redacted in log output:

```php
// Original context
['credentials' => ['key' => 'sk_test_xxx', 'secret' => 'whsec_yyy']]

// After redaction
['credentials.key' => '[REDACTED]', 'credentials.secret' => '[REDACTED]']
```

The redaction matches any key that **contains** any of the configured `redacted_keys`, case-insensitive.

## Database Logs

When `db_logging` is enabled, logs are stored in the `payment_logs` table with structured fields:

```php
use Frolax\Payment\Models\PaymentLog;

// Query logs by gateway
PaymentLog::forGateway('stripe')->latest('occurred_at')->get();

// Query logs by level
PaymentLog::forLevel('error')->get();

// Query logs by category
PaymentLog::forCategory('webhook.received')->get();

// Each log entry contains:
$log->level;          // 'info', 'warning', 'error', 'debug'
$log->category;       // 'payment.create', 'webhook.received', etc.
$log->message;        // Human-readable message
$log->gateway_name;   // 'stripe'
$log->profile;        // 'test'
$log->tenant_id;      // 'tenant-abc'
$log->payment_id;     // 'PAY-001'
$log->attempt_id;     // 'ATT-001'
$log->context_flat;   // Dot-noted key-value pairs (redacted)
$log->context_nested; // Original nested structure (redacted)
$log->occurred_at;    // Timestamp
```

## Manual Logging

You can use the payment logger directly:

```php
use Frolax\Payment\Contracts\PaymentLoggerContract;

class MyService
{
    public function __construct(
        protected PaymentLoggerContract $logger,
    ) {}

    public function doSomething()
    {
        $this->logger->info('custom.operation', 'Starting custom operation', [
            'gateway' => ['name' => 'stripe'],
            'custom_data' => ['key' => 'value'],
        ]);

        $this->logger->error('custom.operation', 'Something went wrong', [
            'error' => ['message' => 'Details here'],
        ]);
    }
}
```

## Dot-Notation Context

Log context is automatically flattened to dot-notation:

```php
// Input (nested)
[
    'gateway' => ['name' => 'stripe'],
    'payment' => ['id' => 'PAY-001', 'amount' => 2999],
]

// Stored as context_flat (dot-notation)
[
    'gateway.name' => 'stripe',
    'payment.id' => 'PAY-001',
    'payment.amount' => 2999,
]
```

This makes database logs easily queryable and filterable.
