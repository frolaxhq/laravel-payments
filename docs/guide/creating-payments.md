# Creating Payments

## Basic Payment Creation

```php
use Frolax\Payment\Facades\Payment;

$result = Payment::gateway('stripe')->create([
    'order' => [
        'id' => 'ORD-001',
        'description' => 'Premium Plan',
    ],
    'money' => [
        'amount' => 2999,
        'currency' => 'USD',
    ],
]);
```

## Full Payload Example

```php
$result = Payment::gateway('stripe')->create([
    'idempotency_key' => 'order-123-attempt-1',

    'order' => [
        'id' => 'ORD-123',
        'description' => 'E-Commerce Order #123',
        'items' => [
            ['name' => 'Widget A', 'quantity' => 2, 'unit_price' => 1000],
            ['name' => 'Widget B', 'quantity' => 1, 'unit_price' => 999],
        ],
    ],

    'money' => ['amount' => 2999, 'currency' => 'USD'],

    'customer' => [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1555123456',
        'address' => [
            'line1' => '456 Oak Ave',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94102',
            'country' => 'US',
        ],
    ],

    'urls' => [
        'return' => route('payments.return', 'stripe'),
        'cancel' => route('payments.cancel', 'stripe'),
        'webhook' => route('payments.webhook', 'stripe'),
    ],

    'context' => [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'locale' => app()->getLocale(),
    ],

    'metadata' => ['user_id' => auth()->id()],
]);
```

## Handling the Result

```php
// Gateway requires redirect (hosted checkout)
if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

// Payment completed immediately
if ($result->isSuccessful()) {
    return view('order.success', [
        'reference' => $result->gatewayReference,
    ]);
}

// Payment is pending
if ($result->isPending()) {
    return view('order.pending');
}

// Payment failed
if ($result->isFailed()) {
    return view('order.failed', [
        'response' => $result->gatewayResponse,
    ]);
}
```

## Fluent Selectors

### Switch Profile

```php
// Use live credentials
Payment::gateway('stripe')
    ->withProfile('live')
    ->create($data);
```

### Multi-Tenant

```php
// Use tenant-specific credentials
Payment::gateway('stripe')
    ->usingContext(['tenant_id' => 'tenant-abc'])
    ->create($data);
```

### Override Credentials

```php
// Use specific credentials for this operation
Payment::gateway('stripe')
    ->usingCredentials(['key' => 'sk_test_...', 'secret' => '...'])
    ->create($data);
```

### Combine Selectors

```php
Payment::gateway('stripe')
    ->withProfile('live')
    ->usingContext(['tenant_id' => 'tenant-abc'])
    ->create($data);
```

## Idempotency

If you provide an `idempotency_key` and persistence is enabled, the Payment manager checks for existing payments with the same key before creating a new one:

```php
// First call: creates the payment
$result = Payment::gateway('stripe')->create([
    'idempotency_key' => 'unique-key-001',
    ...
]);

// Second call with same key: returns existing payment (no duplicate)
$result = Payment::gateway('stripe')->create([
    'idempotency_key' => 'unique-key-001',
    ...
]);
```

## What Happens Internally

1. **Credential Resolution** — Credentials resolved from ENV/DB based on gateway + profile + context
2. **Canonical Payload** — Input array converted to `CanonicalPayload` DTO
3. **Idempotency Check** — If persistence enabled, checks for existing payment with same key
4. **Driver Execution** — `$driver->create($payload, $credentials)` called
5. **Persistence** — Payment record saved to `payments` table, attempt logged to `payment_attempts`
6. **Event Dispatch** — `PaymentCreated` or `PaymentFailed` event fired
7. **Logging** — Operation logged to channel and DB with redacted context
8. **Result** — `GatewayResult` returned to caller
