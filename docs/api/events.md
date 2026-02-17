# Events (API Reference)

All events in `Frolax\Payment\Events`. All events use the `Dispatchable` trait.

## PaymentCreated

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

## PaymentVerified

```php
class PaymentVerified
{
    public string $paymentId;
    public string $gateway;
}
```

## PaymentFailed

```php
class PaymentFailed
{
    public string $paymentId;
    public string $gateway;
    public ?string $reason;
}
```

## PaymentCancelled

```php
class PaymentCancelled
{
    public string $paymentId;
    public string $gateway;
}
```

## PaymentRefundRequested

```php
class PaymentRefundRequested
{
    public string $paymentId;
    public string $gateway;
    public float $amount;
    public string $currency;
}
```

## PaymentRefunded

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

## WebhookReceived

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
