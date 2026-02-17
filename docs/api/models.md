# Models (API Reference)

All Eloquent models in `Frolax\Payment\Models`. All use ULID primary keys via `HasUlids`.

## PaymentModel

```php
use Frolax\Payment\Models\PaymentModel;

// Table: payments (configurable)
PaymentModel::query()
    ->forGateway('stripe')           // scope
    ->forStatus('completed')         // scope
    ->forTenant('tenant-abc')        // scope
    ->pending()                      // scope (status = pending)
    ->completed()                    // scope (status = completed)
    ->get();

// Relationships
$payment->attempts;      // HasMany PaymentAttempt
$payment->webhookEvents; // HasMany PaymentWebhookEvent
$payment->refunds;       // HasMany PaymentRefund
$payment->logs;          // HasMany PaymentLog

// Casts
$payment->amount;            // decimal:2
$payment->canonical_payload; // array
$payment->metadata;          // array
$payment->status;            // PaymentStatus enum
```

## PaymentGateway

```php
use Frolax\Payment\Models\PaymentGateway;

// Casts
$gw->supports; // array
$gw->is_active; // boolean
```

## PaymentGatewayCredential

```php
use Frolax\Payment\Models\PaymentGatewayCredential;

// Casts (encrypted!)
$cred->credentials; // array (auto-encrypted/decrypted)
$cred->is_active;   // boolean
$cred->effective_from; // datetime
$cred->effective_to;   // datetime
```

## PaymentAttempt

```php
use Frolax\Payment\Models\PaymentAttempt;

$attempt->payment; // BelongsTo PaymentModel

// Casts
$attempt->request_payload;  // array
$attempt->response_payload; // array
$attempt->errors;           // array
$attempt->status;           // AttemptStatus enum
```

## PaymentWebhookEvent

```php
use Frolax\Payment\Models\PaymentWebhookEvent;

$event->payment; // BelongsTo PaymentModel (nullable)

// Processing
$event->markProcessed();   // Sets processed=true, processed_at=now
$event->replay();          // Re-process via driver

// Scopes
PaymentWebhookEvent::unprocessed()->get();

// Casts
$event->headers; // array
$event->payload; // array
```

## PaymentRefund

```php
use Frolax\Payment\Models\PaymentRefund;

$refund->payment; // BelongsTo PaymentModel

// Casts
$refund->amount;           // decimal:2
$refund->request_payload;  // array
$refund->response_payload; // array
$refund->metadata;         // array
$refund->status;           // RefundStatus enum
```

## PaymentLog

```php
use Frolax\Payment\Models\PaymentLog;

$log->payment; // BelongsTo PaymentModel (nullable)

// Scopes
PaymentLog::forGateway('stripe')->get();
PaymentLog::forLevel('error')->get();
PaymentLog::forCategory('webhook.received')->get();

// Casts
$log->context_flat;   // array (dot-notation)
$log->context_nested; // array (original)
$log->occurred_at;    // datetime
```
