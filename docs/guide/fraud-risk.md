# Fraud & Risk

Protect your payments with built-in risk scoring and blocklist management.

## Risk Scorer

```php
use Frolax\Payment\Services\RiskScorer;

$scorer = app(RiskScorer::class);

$assessment = $scorer->assess($payment);
// Returns RiskAssessment with score, decision, and factors
```

The scorer evaluates:

| Factor | Description |
|--------|-------------|
| **Blocklist** | Checks email, IP, card fingerprint against blocklist |
| **Velocity** | Detects unusual transaction frequency |
| **Amount** | Flags amounts exceeding configurable thresholds |

## Blocklist

```php
use Frolax\Payment\Models\BlocklistEntry;

// Block an email
BlocklistEntry::create([
    'type' => 'email',
    'value' => 'fraudster@example.com',
    'reason' => 'Confirmed fraud',
]);

// Check if blocked
BlocklistEntry::isBlocked('email', 'fraudster@example.com'); // true
```

Blocklist entries support optional expiry dates for temporary blocks.

## 3D Secure

Gateways can implement `SupportsThreeDSecure` for additional authentication:

```php
use Frolax\Payment\Contracts\SupportsThreeDSecure;

class StripeDriver implements SupportsThreeDSecure
{
    public function initiate3DS(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function verify3DS(string $reference, CredentialsDTO $credentials): GatewayResult { /* ... */ }
}
```

## Configuration

```php
// config/payments.php
'risk' => [
    'high_amount_threshold' => 500,
    'velocity_window_minutes' => 60,
    'velocity_max_transactions' => 10,
],
```
