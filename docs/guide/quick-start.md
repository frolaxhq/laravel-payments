# Quick Start

Build a complete payment flow in 5 minutes.

## 1. Configure a Gateway

Add credentials to `config/payments.php`:

```php
'gateways' => [
    'stripe' => [
        'test' => [
            'key' => env('STRIPE_TEST_KEY'),
            'secret' => env('STRIPE_TEST_SECRET'),
            'webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        ],
    ],
],
```

## 2. Create a Payment

```php
use Frolax\Payment\Facades\Payment;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $result = Payment::gateway('stripe')->create([
            'order' => [
                'id' => 'ORD-' . uniqid(),
                'description' => 'Premium Plan Subscription',
                'items' => [
                    [
                        'name' => 'Premium Plan',
                        'quantity' => 1,
                        'unit_price' => 2999,
                    ],
                ],
            ],
            'money' => [
                'amount' => 2999,
                'currency' => 'USD',
            ],
            'customer' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'urls' => [
                'return' => route('payments.return', 'stripe'),
                'cancel' => route('payments.cancel', 'stripe'),
                'webhook' => route('payments.webhook', 'stripe'),
            ],
        ]);

        // If gateway requires redirect (hosted checkout)
        if ($result->requiresRedirect()) {
            return redirect($result->redirectUrl);
        }

        // If payment was processed immediately
        if ($result->isSuccessful()) {
            return redirect()->route('order.success');
        }

        return redirect()->route('order.pending');
    }
}
```

## 3. Handle the Return

The built-in `ReturnController` handles return callbacks automatically. It verifies the payment with the gateway and redirects to your app.

You can also handle it manually:

```php
class PaymentCallbackController extends Controller
{
    public function return(Request $request)
    {
        $result = Payment::gateway('stripe')->verifyFromRequest($request);

        if ($result->isSuccessful()) {
            // Payment completed
            return view('order.success', [
                'reference' => $result->gatewayReference,
            ]);
        }

        return view('order.failed', [
            'status' => $result->status->value,
        ]);
    }
}
```

## 4. Listen to Events

```php
// app/Providers/EventServiceProvider.php
use Frolax\Payment\Events\PaymentVerified;
use Frolax\Payment\Events\PaymentFailed;

protected $listen = [
    PaymentVerified::class => [
        ActivateSubscription::class,
        SendPaymentReceipt::class,
    ],
    PaymentFailed::class => [
        NotifyPaymentFailure::class,
    ],
];
```

## 5. Process Refunds

```php
$result = Payment::gateway('stripe')->refund([
    'payment_id' => 'PAY-001',
    'money' => ['amount' => 1000, 'currency' => 'USD'],
    'reason' => 'Customer request',
]);

if ($result->isSuccessful()) {
    // Refund processed
}
```

## What's Next?

| Topic | Link |
|-------|------|
| Understand the payload system | [Canonical Payload](/guide/canonical-payload) |
| Learn about driver capabilities | [Capabilities](/guide/capabilities) |
| Set up multi-tenant credentials | [Credentials](/guide/credentials) |
| Configure webhooks | [Webhooks](/guide/webhooks) |
| Create your own gateway | [Creating Drivers](/guide/creating-drivers) |
