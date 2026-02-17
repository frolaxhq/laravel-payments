# Events

Laravel Payments dispatches 7 events throughout the payment lifecycle. Listen to these events to trigger business logic without coupling to the payment flow.

## Event Reference

| Event | Dispatched When | Key Properties |
|-------|----------------|---------------|
| `PaymentCreated` | Payment successfully created | `paymentId`, `gateway`, `amount`, `currency` |
| `PaymentVerified` | Payment verified from callback | `paymentId`, `gateway` |
| `PaymentFailed` | Payment creation failed | `paymentId`, `gateway`, `reason` |
| `PaymentCancelled` | User cancelled payment | `paymentId`, `gateway` |
| `PaymentRefundRequested` | Refund initiated | `paymentId`, `gateway`, `amount`, `currency` |
| `PaymentRefunded` | Refund completed | `paymentId`, `gateway`, `refundReference` |
| `WebhookReceived` | Webhook received from gateway | `gateway`, `eventType`, `gatewayReference`, `payload` |

## Registering Listeners

### Using EventServiceProvider

```php
use Frolax\Payment\Events\PaymentCreated;
use Frolax\Payment\Events\PaymentVerified;
use Frolax\Payment\Events\PaymentFailed;
use Frolax\Payment\Events\WebhookReceived;

protected $listen = [
    PaymentCreated::class => [
        LogPaymentCreation::class,
    ],
    PaymentVerified::class => [
        ActivateSubscription::class,
        SendPaymentReceipt::class,
    ],
    PaymentFailed::class => [
        NotifyPaymentFailure::class,
        RetryPaymentIfAppropriate::class,
    ],
    WebhookReceived::class => [
        ProcessWebhookLogic::class,
    ],
];
```

### Using Closures

```php
use Frolax\Payment\Events\PaymentVerified;

Event::listen(PaymentVerified::class, function ($event) {
    // Activate user's subscription
    $user = User::findByPayment($event->paymentId);
    $user->activateSubscription();
});
```

## Event Properties

### PaymentCreated

```php
class PaymentCreated
{
    public string $paymentId;
    public string $gateway;
    public float $amount;
    public string $currency;
    public ?string $gatewayReference;
    public array $metadata;
}
```

### PaymentVerified

```php
class PaymentVerified
{
    public string $paymentId;
    public string $gateway;
}
```

### PaymentFailed

```php
class PaymentFailed
{
    public string $paymentId;
    public string $gateway;
    public ?string $reason;
}
```

### PaymentCancelled

```php
class PaymentCancelled
{
    public string $paymentId;
    public string $gateway;
}
```

### PaymentRefundRequested

```php
class PaymentRefundRequested
{
    public string $paymentId;
    public string $gateway;
    public float $amount;
    public string $currency;
}
```

### PaymentRefunded

```php
class PaymentRefunded
{
    public string $paymentId;
    public string $gateway;
    public ?string $refundReference;
    public float $amount;
    public string $currency;
}
```

### WebhookReceived

```php
class WebhookReceived
{
    public string $gateway;
    public ?string $eventType;
    public ?string $gatewayReference;
    public bool $signatureValid;
    public array $payload;
    public array $headers;
}
```

## Queued Listeners

For long-running operations, make your listeners queueable:

```php
class ActivateSubscription implements ShouldQueue
{
    public function handle(PaymentVerified $event): void
    {
        // This runs in a queue worker
    }
}
```
