# Subscriptions

Manage recurring billing with full subscription lifecycle support.

## Creating a Subscription

```php
use Frolax\Payment\SubscriptionManager;

$manager = app(SubscriptionManager::class);

$result = $manager->gateway('stripe')->create([
    'plan' => [
        'id' => 'plan_pro',
        'name' => 'Pro Plan',
        'money' => ['amount' => 49.99, 'currency' => 'USD'],
        'interval' => 'monthly',
        'trial_days' => 14,
    ],
    'customer' => [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ],
    'urls' => [
        'return' => route('billing.return'),
        'cancel' => route('billing.cancel'),
    ],
]);
```

## Subscription Lifecycle

```php
// Cancel (immediate or at period end)
$manager->gateway('stripe')->cancel('sub_xxx');

// Pause
$manager->gateway('stripe')->pause('sub_xxx');

// Resume
$manager->gateway('stripe')->resume('sub_xxx');

// Update (change plan, quantity, etc.)
$manager->gateway('stripe')->update('sub_xxx', [
    'plan' => ['id' => 'plan_enterprise'],
    'quantity' => 5,
]);
```

## Subscription Model

The `Subscription` model provides useful state checks:

```php
use Frolax\Payment\Models\Subscription;

$subscription = Subscription::find($id);

$subscription->isActive();    // active or trialing
$subscription->onTrial();     // currently in trial period
$subscription->onGracePeriod(); // cancelled but still within billing period
$subscription->isPaused();
$subscription->isCancelled();
$subscription->isPastDue();
```

## Multi-Item Subscriptions

Support metered billing and multiple line items:

```php
$result = $manager->gateway('stripe')->create([
    'plan' => [
        'id' => 'plan_usage',
        'name' => 'Usage Plan',
        'money' => ['amount' => 0, 'currency' => 'USD'],
        'interval' => 'monthly',
    ],
    'items' => [
        ['product_id' => 'seats', 'name' => 'Team Seats', 'quantity' => 10],
        ['product_id' => 'storage', 'name' => 'Storage GB', 'quantity' => 100],
    ],
]);
```

## Events

| Event | When |
|-------|------|
| `SubscriptionCreated` | New subscription created |
| `SubscriptionRenewed` | Subscription renewed successfully |
| `SubscriptionPaused` | Subscription paused |
| `SubscriptionResumed` | Subscription resumed |
| `SubscriptionCancelled` | Subscription cancelled |
| `SubscriptionTrialEnding` | Trial period ending soon |

## Enums

### SubscriptionStatus

`Active`, `Trialing`, `PastDue`, `Paused`, `Cancelled`, `Expired`, `Incomplete`

### BillingInterval

`Daily`, `Weekly`, `Monthly`, `Quarterly`, `Yearly`, `Custom`

## Gateway Implementation

Your gateway driver needs to implement `SupportsRecurring`:

```php
use Frolax\Payment\Contracts\SupportsRecurring;

class StripeDriver extends AbstractGatewayDriver implements SupportsRecurring
{
    public function createSubscription(CanonicalSubscriptionPayload $payload, CredentialsDTO $credentials): GatewayResult
    {
        // Create subscription via Stripe API
    }

    public function cancelSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function pauseSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function resumeSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function updateSubscription(string $subscriptionId, array $data, CredentialsDTO $credentials): GatewayResult { /* ... */ }
}
```
