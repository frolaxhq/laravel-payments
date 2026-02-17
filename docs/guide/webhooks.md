# Webhooks

Laravel Payments provides a universal webhook endpoint that handles incoming gateway notifications with signature verification, idempotency, and replay safety.

## Built-in Webhook Route

```
POST /payments/webhook/{gateway}
```

The route is registered automatically. Configure it in `config/payments.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'payments',
    'webhook_middleware' => [], // No CSRF for webhooks
],
```

::: tip
The webhook route uses **no CSRF middleware** by default. If you're using `web` middleware globally, ensure you exclude the webhook path from CSRF verification.
:::

## How It Works

```
Gateway Server ──POST──► /payments/webhook/stripe
                              │
                              ▼
                    1. Verify signature (if driver supports it)
                    2. Check idempotency (duplicate detection)
                    3. Store webhook event in DB
                    4. Dispatch WebhookReceived event
                    5. Process via driver->verify()
                    6. Update payment status
                    7. Mark webhook as processed
                              │
                              ▼
                       Response: 200 OK
```

## Signature Verification

If the gateway driver implements `SupportsWebhookVerification`, the webhook controller automatically:

1. Calls `verifyWebhookSignature()` to validate the request
2. Returns `403 Invalid signature` if verification fails
3. Parses event type and gateway reference for processing

## Idempotency

Webhooks are deduplicated by `(gateway_name, gateway_reference, event_type)`. If the same webhook is received twice, the second request returns `200 Already processed` without re-processing.

## Webhook Storage

When persistence is enabled, every webhook is stored in the `payment_webhook_events` table:

```php
use Frolax\Payment\Models\PaymentWebhookEvent;

// View recent webhooks
$events = PaymentWebhookEvent::where('gateway_name', 'stripe')
    ->latest()
    ->get();

// Check processing status
$event->processed;    // bool
$event->processed_at; // timestamp

// View raw data
$event->headers;      // array
$event->payload;      // array
```

## Replay Webhooks

Re-process a stored webhook event via CLI:

```bash
php artisan payments:webhooks:replay {webhook_event_id}
```

This safely reconstructs the original request and processes it through the driver again. The command:

1. Loads the stored webhook event
2. Confirms details with the operator
3. Reconstructs the HTTP request from stored headers + payload
4. Processes through the driver's `verify()` method
5. Updates the payment status
6. Re-dispatches the `WebhookReceived` event
7. Marks the event as processed

## Listening to Webhooks

Listen for the `WebhookReceived` event in your application:

```php
use Frolax\Payment\Events\WebhookReceived;

class HandleWebhook
{
    public function handle(WebhookReceived $event)
    {
        $event->gateway;          // 'stripe'
        $event->eventType;        // 'payment.completed'
        $event->gatewayReference; // 'ch_1234'
        $event->signatureValid;   // true
        $event->payload;          // [...raw data...]
        $event->headers;          // [...HTTP headers...]
    }
}
```

## CSRF Exclusion

If your Laravel app applies CSRF middleware globally, add the webhook path to your exceptions:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'payments/webhook/*',
];
```

Or in Laravel 11+:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payments/webhook/*',
    ]);
})
```
