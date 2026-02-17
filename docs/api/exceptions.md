# Exceptions (API Reference)

All exceptions in `Frolax\Payment\Exceptions`. All extend `RuntimeException`.

## GatewayNotFoundException

Thrown when a requested gateway is not registered.

```php
throw new GatewayNotFoundException('stripe');
// Message: "Gateway [stripe] not found."
```

## MissingCredentialsException

Thrown when credentials cannot be resolved for a gateway + profile.

```php
throw MissingCredentialsException::forGateway('stripe', 'live');
// Message: "Missing credentials for gateway [stripe] with profile [live]."
```

## InvalidSignatureException

Thrown when webhook signature verification fails.

```php
throw new InvalidSignatureException('stripe');
// Message: "Invalid webhook signature for gateway [stripe]."
```

## UnsupportedCapabilityException

Thrown when a driver doesn't implement a required capability.

```php
throw new UnsupportedCapabilityException('stripe', 'refund');
// Message: "Gateway [stripe] does not support [refund]."
```

## InvalidCanonicalPayloadException

Thrown when the canonical payload fails validation.

```php
throw new InvalidCanonicalPayloadException([
    'order.id' => 'required',
    'money.amount' => 'must be positive',
]);
```

Properties:
- `getErrors(): array` — Validation error details

## GatewayRequestFailedException

Thrown when a request to the gateway API fails.

```php
throw GatewayRequestFailedException::withResponse('stripe', $response);
```

Properties:
- `getGatewayResponse(): array` — Raw response from the gateway

## VerificationMismatchException

Thrown when payment verification returns unexpected results.

```php
throw new VerificationMismatchException('stripe');
// Message: "Verification mismatch for gateway [stripe]."
```
