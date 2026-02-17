# Payouts & Splits

Support marketplace-style payouts and split payments.

## Payout Recipients

```php
use Frolax\Payment\Models\PayoutRecipient;

$recipient = PayoutRecipient::create([
    'gateway_name' => 'stripe',
    'external_id' => 'acct_xxx',
    'owner_type' => 'App\Models\Vendor',
    'owner_id' => $vendor->id,
    'metadata' => ['business_name' => 'Acme Corp'],
]);
```

## Creating Payouts

```php
use Frolax\Payment\Models\Payout;

$payout = Payout::create([
    'payout_recipient_id' => $recipient->id,
    'gateway_name' => 'stripe',
    'amount' => 500.00,
    'currency' => 'USD',
    'status' => 'pending',
    'scheduled_at' => now()->addDay(),
]);
```

## Split Payments

Split a single payment across multiple recipients:

```php
use Frolax\Payment\Models\PaymentSplit;

PaymentSplit::create([
    'payment_id' => $payment->id,
    'payout_recipient_id' => $vendor->id,
    'amount' => 85.00,
    'currency' => 'USD',
    'type' => 'percentage',
    'split_value' => 85,
]);
```

## Gateway Implementation

Implement `SupportsPayout` in your gateway driver:

```php
use Frolax\Payment\Contracts\SupportsPayout;

class StripeDriver implements SupportsPayout
{
    public function createPayout(array $data, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function getPayout(string $payoutId, CredentialsDTO $credentials): GatewayResult { /* ... */ }
    public function listPayouts(array $filters, CredentialsDTO $credentials): array { /* ... */ }
}
```
