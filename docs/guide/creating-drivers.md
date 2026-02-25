# Creating Gateway Drivers

Build a custom gateway driver for any payment provider. Drivers are simple PHP classes that implement the
`GatewayDriverContract` interface.

## Generate a Driver Skeleton

The fastest way to create a driver:

```bash
php artisan payments:make-gateway Bkash \
    --key=bkash \
    --display="bKash" \
    --capabilities=redirect,webhook,refund \
    --credentials=app_key:required,app_secret:required,username:required,password:required
```

This generates:

```
app/Payment/Gateways/Bkash/
├── BkashDriver.php       # Driver implementation
├── BkashDriverTest.php   # Test scaffold (in tests/)
├── config_snippet.php    # Config to merge into payments.php
└── README.md             # Driver documentation
```

## Implementing a Driver

### Step 1: Implement the Contract

```php
<?php

namespace App\Payment\Gateways\Bkash;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsHostedRedirect;
use Frolax\Payment\Contracts\SupportsWebhookVerification;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BkashDriver implements
    GatewayDriverContract,
    SupportsHostedRedirect,
    SupportsWebhookVerification
{
    protected ?Credentials $credentials = null;

    public function name(): string
    {
        return 'bkash';
    }

    public function setCredentials(Credentials $credentials): static
    {
        $this->credentials = $credentials;
        return $this;
    }

    public function capabilities(): array
    {
        return ['redirect', 'webhook'];
    }

    // ...
}
```

### Step 2: Implement `create()`

Map the canonical payload to the gateway's API:

```php
public function create(Payload $payload, Credentials $credentials): GatewayResult
{
    // 1. Authenticate with the gateway
    $token = $this->getAuthToken($credentials);

    // 2. Map canonical payload to gateway-specific fields
    $response = Http::withToken($token)
        ->post('https://api.bkash.com/v1/checkout/create', [
            'mode' => '0011',
            'payerReference' => $payload->customer?->phone ?? '',
            'callbackURL' => $payload->urls?->return ?? '',
            'amount' => $payload->money->amount,
            'currency' => $payload->money->currency,
            'intent' => 'sale',
            'merchantInvoiceNumber' => $payload->order->id,
        ]);

    $data = $response->json();

    // 3. Return uniform GatewayResult
    if ($response->successful() && isset($data['bkashURL'])) {
        return new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: $data['paymentID'] ?? null,
            redirectUrl: $data['bkashURL'],
            gatewayResponse: $data,
        );
    }

    return new GatewayResult(
        status: PaymentStatus::Failed,
        gatewayResponse: $data,
    );
}
```

### Step 3: Implement `verify()`

Verify payment status from a callback or return request:

```php
public function verify(Request $request, Credentials $credentials): GatewayResult
{
    $paymentId = $request->input('paymentID');
    $token = $this->getAuthToken($credentials);

    $response = Http::withToken($token)
        ->post('https://api.bkash.com/v1/checkout/execute', [
            'paymentID' => $paymentId,
        ]);

    $data = $response->json();

    $status = match ($data['transactionStatus'] ?? '') {
        'Completed' => PaymentStatus::Completed,
        'Initiated' => PaymentStatus::Pending,
        default => PaymentStatus::Failed,
    };

    return new GatewayResult(
        status: $status,
        gatewayReference: $data['trxID'] ?? $paymentId,
        gatewayResponse: $data,
    );
}
```

### Step 4: Implement Capability Methods

```php
// SupportsHostedRedirect
public function getRedirectUrl(GatewayResult $result): ?string
{
    return $result->redirectUrl;
}

// SupportsWebhookVerification
public function verifyWebhookSignature(Request $request, Credentials $credentials): bool
{
    $signature = $request->header('X-Bkash-Signature');
    $webhookSecret = $credentials->get('webhook_secret');
    $computedSignature = hash_hmac('sha256', $request->getContent(), $webhookSecret);

    return hash_equals($computedSignature, $signature);
}

public function parseWebhookEventType(Request $request): ?string
{
    return $request->input('event_type', 'payment.completed');
}

public function parseWebhookGatewayReference(Request $request): ?string
{
    return $request->input('trxID');
}
```

### Step 5: Register the Driver

```php
// In a service provider:
use Frolax\Payment\GatewayRegistry;

public function boot(GatewayRegistry $registry): void
{
    $registry->register(
        key: 'bkash',
        driver: fn () => new BkashDriver(),
        displayName: 'bKash',
        capabilities: ['redirect', 'webhook'],
    );
}
```

### Step 6: Add Credentials

```php
// config/payments.php
'gateways' => [
    'bkash' => [
        'test' => [
            'app_key' => env('BKASH_TEST_APP_KEY'),
            'app_secret' => env('BKASH_TEST_APP_SECRET'),
            'username' => env('BKASH_TEST_USERNAME'),
            'password' => env('BKASH_TEST_PASSWORD'),
        ],
    ],
],
```

## Testing Your Driver

```php
test('bkash driver creates a payment', function () {
    Http::fake([
        'api.bkash.com/*' => Http::response([
            'paymentID' => 'PMT-001',
            'bkashURL' => 'https://bkash.com/checkout/PMT-001',
        ]),
    ]);

    $driver = new BkashDriver();

    $payload = Payload::fromArray([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 500, 'currency' => 'BDT'],
    ]);

    $credentials = new Credentials(
        gateway: 'bkash',
        profile: 'test',
        credentials: ['app_key' => 'key', 'app_secret' => 'secret'],
    );

    $result = $driver->create($payload, $credentials);

    expect($result->requiresRedirect())->toBeTrue();
    expect($result->redirectUrl)->toContain('bkash.com');
});
```

## Next Steps

- [Creating Addon Packages](/guide/creating-addons) — Package your driver for distribution
- [Auto-Discovery](/guide/auto-discovery) — How addon auto-discovery works
