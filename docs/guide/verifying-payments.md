# Verifying Payments

After a customer completes payment on a hosted checkout page, they're redirected back to your application. The verification step confirms the payment status with the gateway.

## Automatic Verification (Built-in Routes)

The package provides built-in routes that handle verification automatically:

- `GET /payments/return/{gateway}` — Verifies and redirects
- `GET /payments/cancel/{gateway}` — Marks as cancelled and redirects

These routes fire events and update payment records automatically. Configure them in `config/payments.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'payments',
    'middleware' => ['web'],
    'webhook_middleware' => [],
],
```

## Manual Verification

If you prefer to handle verification in your own controller:

```php
use Frolax\Payment\Facades\Payment;

class PaymentCallbackController extends Controller
{
    public function handleReturn(Request $request, string $gateway)
    {
        $result = Payment::gateway($gateway)->verifyFromRequest($request);

        return match (true) {
            $result->isSuccessful() => redirect()->route('order.success', [
                'reference' => $result->gatewayReference,
            ]),

            $result->isPending() => redirect()->route('order.pending'),

            default => redirect()->route('order.failed', [
                'error' => $result->status->value,
            ]),
        };
    }
}
```

## Verification Flow

```
Customer ──► Gateway Checkout ──► Gateway Return URL ──► Your App
                                                          │
                                                          ▼
                                                   verifyFromRequest()
                                                          │
                                                          ▼
                                                   driver->verify()
                                                          │
                                                   ┌──────┴──────┐
                                                   │             │
                                                   ▼             ▼
                                              Completed       Failed
                                                   │             │
                                                   ▼             ▼
                                           PaymentVerified  PaymentFailed
                                              (event)        (event)
```

## GatewayResult from Verification

The `verify()` call returns the same `GatewayResult` as `create()`:

```php
$result = Payment::gateway('stripe')->verifyFromRequest($request);

$result->status;            // PaymentStatus enum
$result->gatewayReference;  // Gateway's transaction ID
$result->gatewayResponse;   // Raw gateway response data
$result->isSuccessful();    // true if completed
$result->isPending();       // true if still processing
$result->isFailed();        // true if failed
```

## Webhook-Based Verification

For gateways that confirm payment status via webhooks (not return URLs), see [Webhooks](/guide/webhooks).
