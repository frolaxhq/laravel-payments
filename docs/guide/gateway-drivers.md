# Gateway Drivers

Gateway drivers are the bridge between the canonical payload and a specific payment gateway's API. Each driver implements `GatewayDriverContract` and translates the standard payload into gateway-specific requests.

## Driver Contract

Every driver must implement `GatewayDriverContract`:

```php
interface GatewayDriverContract
{
    /**
     * Unique gateway key (e.g., 'stripe', 'bkash').
     */
    public function name(): string;

    /**
     * Create a payment from the canonical payload.
     */
    public function create(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Verify a payment from a gateway callback/return request.
     */
    public function verify(Request $request, CredentialsDTO $credentials): GatewayResult;

    /**
     * Set active credentials on the driver.
     */
    public function setCredentials(CredentialsDTO $credentials): static;

    /**
     * List capabilities this driver supports.
     */
    public function capabilities(): array;
}
```

## Gateway Result

Every driver method returns a `GatewayResult`:

```php
final readonly class GatewayResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public ?string $redirectUrl = null,
        public array $gatewayResponse = [],
        public array $metadata = [],
    ) {}

    public function isSuccessful(): bool;
    public function isPending(): bool;
    public function isFailed(): bool;
    public function requiresRedirect(): bool;
}
```

### Status Check Methods

```php
$result = Payment::gateway('stripe')->create($data);

$result->isSuccessful();      // PaymentStatus::Completed
$result->isPending();         // PaymentStatus::Pending
$result->isFailed();          // PaymentStatus::Failed
$result->requiresRedirect();  // Has a non-null redirectUrl
```

## How Drivers Work

```
┌──────────────┐     ┌───────────────┐     ┌──────────────────┐
│ Canonical     │     │ Driver        │     │ Gateway API      │
│ Payload       │ ──► │ (maps fields) │ ──► │ (Stripe, bKash)  │
│ (immutable)   │     │               │     │                  │
└──────────────┘     └───────────────┘     └──────────────────┘
                            │
                            ▼
                     ┌───────────────┐
                     │ GatewayResult │
                     │ (uniform)     │
                     └───────────────┘
```

The driver is responsibility for:

1. **Mapping** canonical fields to gateway-specific fields
2. **Making HTTP requests** to the gateway API
3. **Parsing responses** into a uniform `GatewayResult`
4. **Handling errors** by throwing appropriate exceptions

## Registering Drivers

### Via Configuration

```php
// config/payments.php
'gateways' => [
    'stripe' => [
        'driver' => \App\Payment\Gateways\Stripe\StripeDriver::class,
        'test' => [...],
        'live' => [...],
    ],
],
```

### Via Service Provider

```php
use Frolax\Payment\GatewayRegistry;

public function boot(GatewayRegistry $registry): void
{
    $registry->register(
        key: 'stripe',
        driver: fn () => new StripeDriver(),
        displayName: 'Stripe',
        capabilities: ['redirect', 'webhook', 'refund', 'status'],
    );
}
```

### Via Auto-Discovery

Gateway addon packages register automatically. See [Creating Addon Packages](/guide/creating-addons).

## Next Steps

- [Capabilities](/guide/capabilities) — Learn about capability interfaces
- [Creating Drivers](/guide/creating-drivers) — Build your own gateway driver
