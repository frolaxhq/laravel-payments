# Developer Tools

Tools to accelerate development and testing of payment integrations.

## Payment Links

Create shareable payment links for quick checkout:

```php
use Frolax\Payment\Models\PaymentLink;

$link = PaymentLink::create([
    'gateway_name' => 'stripe',
    'title' => 'Invoice #1234',
    'description' => 'Payment for consulting services',
    'amount' => 500.00,
    'currency' => 'USD',
    'is_single_use' => true,
    'expires_at' => now()->addDays(7),
]);

$url = $link->getUrl(); // https://your-app.com/payments/link/abc123
```

## Sandbox Simulator

Test payment flows without connecting to a real gateway:

```php
use Frolax\Payment\Services\SandboxSimulator;

$simulator = app(SandboxSimulator::class);

// Simulate a successful payment
$result = $simulator->simulateCreate($payload);

// Simulate a failed payment (include "FAIL" in order ID)
$failPayload = CanonicalPayload::fromArray([
    'order' => ['id' => 'ORD-FAIL-001'],
    'money' => ['amount' => 100, 'currency' => 'USD'],
]);
$result = $simulator->simulateCreate($failPayload); // Failed status

// Simulate a refund
$result = $simulator->simulateRefund('PAY-001', 50.00);

// Generate a webhook payload
$webhook = $simulator->simulateWebhook('payment.completed', 'GW-REF-001');
```

## Schema Validator

Pre-validate payloads before sending to gateways:

```php
use Frolax\Payment\Services\SchemaValidator;

$validator = app(SchemaValidator::class);

// Register gateway-specific rules
$validator->forGateway('stripe', [
    'customer.email' => ['required'],
    'metadata.stripe_customer' => ['required'],
]);

// Validate
$errors = $validator->validate($payloadArray, 'stripe');

if ($validator->passes($payloadArray, 'stripe')) {
    // Safe to proceed
}
```

## Advanced Webhooks

### Webhook Router

Route incoming webhook events to handler classes:

```php
use Frolax\Payment\Services\WebhookRouter;

$router = app(WebhookRouter::class);

// Register handlers
$router->route('payment.completed', App\Handlers\PaymentCompleted::class);
$router->route('subscription.*', App\Handlers\SubscriptionHandler::class);

// Resolve a handler
$handler = $router->resolve('payment.completed');
```

### Webhook Retry Policy

Configure retry behavior for failed webhook deliveries:

```php
// config/payments.php
'webhooks' => [
    'retry_attempts' => 5,
    'retry_backoff' => 'exponential', // 'exponential', 'linear', or 'fixed'
    'retry_delay_seconds' => 60,
],
```

## Tokenization

Save and reuse payment methods:

```php
// Gateway implements SupportsTokenization
$result = Payment::gateway('stripe')->tokenize($payload, $credentials);
$chargeResult = Payment::gateway('stripe')->chargeToken($token, $payload, $credentials);
```

## Payment Method Capability Contracts

Extend gateways with additional payment method support:

| Contract | Use Case |
|----------|----------|
| `SupportsWallets` | Apple Pay, Google Pay, mobile wallets |
| `SupportsBankTransfer` | Direct bank/wire transfers |
| `SupportsBuyNowPayLater` | Klarna, Afterpay, Tabby |
| `SupportsQRCode` | QR code-based payments |
| `SupportsCOD` | Cash on delivery tracking |
