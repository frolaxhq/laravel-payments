# Facade (API Reference)

## Payment Facade

```php
use Frolax\Payment\Facades\Payment;
```

### Methods

#### `gateway(?string $name = null): Payment`

Select a gateway. If null, uses the default gateway from config.

```php
Payment::gateway('stripe');
Payment::gateway(); // uses config('payments.default')
```

#### `withProfile(string $profile): Payment`

Set the credential profile for this operation.

```php
Payment::gateway('stripe')->withProfile('live');
```

#### `usingContext(array $context): Payment`

Set runtime context (e.g., tenant_id).

```php
Payment::gateway('stripe')->usingContext(['tenant_id' => 'abc']);
```

#### `usingCredentials(array $credentials): Payment`

Override credentials for this single operation.

```php
Payment::gateway('stripe')->usingCredentials([
    'key' => 'sk_test_...',
    'secret' => '...',
]);
```

#### `create(array $data): GatewayResult`

Create a payment from the canonical payload data.

```php
$result = Payment::gateway('stripe')->create([
    'order' => ['id' => 'ORD-001'],
    'money' => ['amount' => 100, 'currency' => 'USD'],
]);
```

#### `verifyFromRequest(Request $request): GatewayResult`

Verify a payment from a gateway callback request.

```php
$result = Payment::gateway('stripe')->verifyFromRequest($request);
```



#### `status(array $data): GatewayResult`

Query payment status. Gateway driver must implement `SupportsStatusQuery`.

```php
$result = Payment::gateway('stripe')->status([
    'payment_id' => 'PAY-001',
]);
```

### Fluent Chaining

All selectors can be chained:

```php
$result = Payment::gateway('stripe')
    ->withProfile('live')
    ->usingContext(['tenant_id' => 'tenant-abc'])
    ->create($data);
```
