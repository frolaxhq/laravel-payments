# Canonical Payload

The canonical payload is the core concept of Laravel Payments. It's a single, immutable DTO structure that every gateway
driver receives—regardless of the gateway's native API format.

## Why Canonical?

Traditional payment integrations require you to build gateway-specific payloads:

```php
// ❌ Without canonical payload — gateway-specific code everywhere

// Stripe
$stripe->checkout->sessions->create([
    'line_items' => [['price_data' => ['unit_amount' => 2999, ...]]],
    'mode' => 'payment',
    'success_url' => '...',
]);

// bKash
$bkash->createPayment([
    'amount' => '29.99',
    'intent' => 'sale',
    'merchantInvoiceNumber' => 'INV-001',
]);
```

With the canonical payload, your application code is the same for every gateway:

```php
// ✅ With canonical payload — one shape, any gateway
Payment::gateway($anyGateway)->create([
    'order' => ['id' => 'ORD-001'],
    'money' => ['amount' => 2999, 'currency' => 'USD'],
]);
```

## Payload Structure

```php
Payload::fromArray([
    // Auto-generated if not provided
    'idempotency_key' => 'order-123-attempt-1',

    // Order information (required)
    'order' => [
        'id' => 'ORD-001',                    // required
        'description' => 'Premium Plan',       // optional
        'items' => [                           // optional
            [
                'name' => 'Premium Plan',
                'quantity' => 1,
                'unit_price' => 2999,
                'description' => 'Monthly subscription',
                'sku' => 'PLAN-PREMIUM',
                'metadata' => [],
            ],
        ],
    ],

    // Money (required)
    'money' => [
        'amount' => 2999,     // required, integer or float
        'currency' => 'USD',  // required, 3-letter ISO code
    ],

    // Customer information (optional)
    'customer' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'address' => [
            'line1' => '123 Main St',
            'line2' => 'Apt 4B',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
        ],
    ],

    // URLs (optional — auto-filled from route config if not set)
    'urls' => [
        'return' => 'https://myapp.com/payments/return/stripe',
        'cancel' => 'https://myapp.com/payments/cancel/stripe',
        'webhook' => 'https://myapp.com/payments/webhook/stripe',
    ],

    // Request context (optional — auto-captured if available)
    'context' => [
        'ip' => '203.0.113.1',
        'user_agent' => 'Mozilla/5.0...',
        'locale' => 'en_US',
    ],

    // Freeform metadata (optional — stored as-is)
    'metadata' => [
        'plan' => 'premium',
        'user_id' => 42,
    ],

    // Gateway-specific overrides (optional — passed to driver only)
    'extra' => [
        'stripe_payment_method_types' => ['card'],
    ],
]);
```

## Field Reference

### Required Fields

| Field            | Type         | Description                       |
|------------------|--------------|-----------------------------------|
| `order.id`       | `string`     | Your application's order ID       |
| `money.amount`   | `int\|float` | Payment amount (must be positive) |
| `money.currency` | `string`     | 3-letter ISO 4217 currency code   |

### Optional Fields

| Field                | Type     | Description                               |
|----------------------|----------|-------------------------------------------|
| `idempotency_key`    | `string` | Unique key for duplicate-safe operations  |
| `order.description`  | `string` | Human-readable order description          |
| `order.items[]`      | `array`  | Line items (name, quantity, unit_price)   |
| `customer.name`      | `string` | Customer full name                        |
| `customer.email`     | `string` | Customer email address                    |
| `customer.phone`     | `string` | Customer phone number                     |
| `customer.address.*` | `object` | Full address (line1, city, country, etc.) |
| `urls.return`        | `string` | URL to redirect after payment             |
| `urls.cancel`        | `string` | URL to redirect on cancellation           |
| `urls.webhook`       | `string` | URL for gateway webhook notifications     |
| `context.ip`         | `string` | Client IP address                         |
| `context.user_agent` | `string` | Client user agent                         |
| `context.locale`     | `string` | Preferred locale                          |
| `metadata`           | `array`  | Freeform key-value metadata               |
| `extra`              | `array`  | Driver-specific overrides                 |

## Idempotency

Every canonical payload carries an `idempotency_key`. If you don't provide one, it's auto-generated from the order ID
and amount:

```php
// Auto-generated: sha256(order_id + amount + currency)
$payload = Payload::fromArray([...]);
echo $payload->idempotencyKey; // "a3f8b2c1..."
```

The Payment manager uses this key to prevent duplicate payment creation when persistence is enabled.

## Dot-Notation Flattening

The canonical payload supports dot-notation flattening for logging and debugging:

```php
$payload->toDotArray();
// [
//     'idempotency_key' => 'order-123-attempt-1',
//     'order.id' => 'ORD-001',
//     'order.description' => 'Premium Plan',
//     'money.amount' => 2999,
//     'money.currency' => 'USD',
//     'customer.name' => 'John Doe',
//     'customer.email' => 'john@example.com',
//     ...
// ]
```

## Refund Payload

Refunds use a separate `RefundPayload`:

```php
RefundPayload::fromArray([
    'payment_id' => 'PAY-001',
    'gateway_reference' => 'ch_1234',
    'money' => ['amount' => 1000, 'currency' => 'USD'],
    'reason' => 'Customer request',
    'metadata' => ['refund_ticket' => 'REF-001'],
]);
```

## Status Query Payload

Status queries use `StatusPayload`:

```php
StatusPayload::fromArray([
    'payment_id' => 'PAY-001',
    'gateway_reference' => 'ch_1234',
]);
```
