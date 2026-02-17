# Capabilities

Capabilities are interfaces that gateway drivers can implement to declare the features they support. The core package never branches on gateway names—instead, it checks which capability interfaces a driver implements.

## Available Capabilities

### SupportsHostedRedirect

For gateways that redirect users to a hosted checkout page.

```php
interface SupportsHostedRedirect
{
    /**
     * Extract the redirect URL from a create result.
     */
    public function getRedirectUrl(GatewayResult $result): ?string;
}
```

**Example usage:**

```php
$result = Payment::gateway('stripe')->create($data);

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}
```

---

### SupportsWebhookVerification

For gateways that send webhook notifications with verifiable signatures.

```php
interface SupportsWebhookVerification
{
    /**
     * Verify the webhook signature from the incoming request.
     */
    public function verifyWebhookSignature(Request $request, CredentialsDTO $credentials): bool;

    /**
     * Parse the event type from the webhook payload.
     */
    public function parseWebhookEventType(Request $request): ?string;

    /**
     * Parse the gateway reference from the webhook payload.
     */
    public function parseWebhookGatewayReference(Request $request): ?string;
}
```

The built-in `WebhookController` automatically uses this interface to verify incoming webhooks.

---

### SupportsRefund

For gateways that support refund operations.

```php
interface SupportsRefund
{
    /**
     * Process a refund through the gateway.
     */
    public function refund(CanonicalRefundPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

**Example usage:**

```php
$result = Payment::gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'money' => ['amount' => 1000, 'currency' => 'USD'],
    'reason' => 'Customer request',
]);
```

If a driver doesn't implement `SupportsRefund`, calling `refund()` throws `UnsupportedCapabilityException`.

---

### SupportsStatusQuery

For gateways that allow querying payment status.

```php
interface SupportsStatusQuery
{
    /**
     * Query the current status of a payment.
     */
    public function status(CanonicalStatusPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

**Example usage:**

```php
$result = Payment::gateway('stripe')->status([
    'payment_id' => 'PAY-001',
    'gateway_reference' => 'ch_1234',
]);
```

---

### SupportsTokenization <Badge type="info" text="Future" />

For gateways that support saved payment methods and tokenized payments.

```php
interface SupportsTokenization
{
    public function tokenize(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function chargeToken(string $token, CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
```

---

### SupportsInstallments <Badge type="info" text="Future" />

For gateways that support EMI or installment payment plans.

```php
interface SupportsInstallments
{
    public function installmentPlans(CanonicalPayload $payload, CredentialsDTO $credentials): array;
}
```

## Checking Capabilities

You can check a driver's capabilities at runtime:

```php
$driver = $registry->resolve('stripe');

// Check via capabilities array
in_array('refund', $driver->capabilities());

// Check via interface
$driver instanceof SupportsRefund;

// The Payment manager checks automatically and throws
// UnsupportedCapabilityException if a capability is missing
```

## Capability Matrix Example

| Gateway | Redirect | Webhooks | Refund | Status | Tokenization |
|---------|----------|----------|--------|--------|-------------|
| Stripe | ✅ | ✅ | ✅ | ✅ | 🔜 |
| bKash | ✅ | ✅ | ✅ | ✅ | ❌ |
| PayPal | ✅ | ✅ | ✅ | ✅ | 🔜 |
| Nagad | ✅ | ✅ | ❌ | ✅ | ❌ |
