# Refunds

Process refunds through the same canonical interface used for payments. The gateway driver handles the API-specific refund logic.

## Prerequisites

The gateway driver must implement `SupportsRefund`. If it doesn't, calling `refund()` throws `UnsupportedCapabilityException`.

## Basic Refund

```php
use Frolax\Payment\RefundManager;

$manager = app(RefundManager::class);

$result = $manager->gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'money' => [
        'amount' => 1000,
        'currency' => 'USD',
    ],
    'reason' => 'Customer request',
]);
```

## Full Refund Payload

```php
$result = $manager->gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'gateway_reference' => 'ch_1234',          // Gateway's original txn ID
    'money' => ['amount' => 1000, 'currency' => 'USD'],
    'reason' => 'Defective product',
    'metadata' => [
        'refund_ticket' => 'REF-001',
        'initiated_by' => 'admin@example.com',
    ],
]);
```

## Handling the Result

```php
if ($result->isSuccessful()) {
    // Refund processed — PaymentRefunded event dispatched
    $refundReference = $result->gatewayReference;
}

if ($result->isPending()) {
    // Refund submitted but not yet confirmed by gateway
}

if ($result->isFailed()) {
    // Refund failed — check $result->gatewayResponse for details
}
```

## Events

| Event | When |
|-------|------|
| `PaymentRefundRequested` | Before the refund is sent to the gateway |
| `PaymentRefunded` | After the refund is confirmed by the gateway |

## Refund Records

When persistence is enabled, refunds are stored in the `payment_refunds` table:

```php
use Frolax\Payment\Models\PaymentRefund;

// Get all refunds for a payment
$refunds = PaymentRefund::where('payment_id', $paymentId)->get();

// Relationships
$refund->payment; // BelongsTo PaymentModel
```

## Partial Refunds

Partial refunds are supported if the gateway driver supports them:

```php
// Refund only part of the payment
$manager->gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'money' => ['amount' => 500, 'currency' => 'USD'], // Half of original
]);
```
