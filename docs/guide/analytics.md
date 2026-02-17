# Analytics & Reporting

Track revenue, monitor gateway health, and analyze conversion funnels.

## Revenue Analytics

```php
use Frolax\Payment\Services\RevenueAnalytics;

$analytics = app(RevenueAnalytics::class);
```

### Revenue Summary

```php
$summary = $analytics->revenueSummary(
    from: now()->subDays(30),
    to: now(),
    currency: 'USD'
);
// ['total_revenue' => 12500.00, 'total_payments' => 156, 'average_payment' => 80.13, ...]
```

### MRR & ARR

```php
$mrr = $analytics->mrr('USD');
// ['mrr' => 5000.00, 'arr' => 60000.00, 'active_subscriptions' => 100]
```

### Gateway Success Rates

```php
$rates = $analytics->gatewaySuccessRates(
    from: now()->subDays(7)
);
// ['stripe' => ['success_rate' => 98.5, 'total' => 200, 'successful' => 197], ...]
```

### Conversion Funnel

```php
$funnel = $analytics->conversionFunnel(
    from: now()->subDays(30)
);
// ['initiated' => 500, 'completed' => 420, 'failed' => 30, 'abandoned' => 50, 'conversion_rate' => 84.0]
```
