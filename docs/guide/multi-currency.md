# Multi-Currency & FX

Convert between currencies with built-in exchange rate management.

## Currency Converter

```php
use Frolax\Payment\Services\CurrencyConverter;

$converter = app(CurrencyConverter::class);

// Convert amounts
$result = $converter->convert(100.00, 'USD', 'EUR');
// Returns: ['original_amount' => 100, 'converted_amount' => 92.50, 'rate' => 0.925, ...]

// Same currency returns identity
$result = $converter->convert(100.00, 'USD', 'USD');
// Returns: ['rate' => 1.0, 'source' => 'identity']
```

The converter automatically resolves reverse rates when a direct rate isn't available.

## Managing Exchange Rates

```php
// Store a rate
$converter->setRate('USD', 'EUR', 0.925, 'ecb_api');

// Or use the model directly
use Frolax\Payment\Models\ExchangeRate;

ExchangeRate::create([
    'from_currency' => 'USD',
    'to_currency' => 'BDT',
    'rate' => 109.50,
    'source' => 'central_bank',
    'fetched_at' => now(),
]);

// Get latest rate
$rate = ExchangeRate::latest('USD', 'BDT');
$converted = $rate->convert(100); // 10,950.00
```

## Integration with Payments

Use the converter before sending payments to gateways that require specific currencies:

```php
$converter = app(CurrencyConverter::class);
$result = $converter->convert($amount, $userCurrency, $gatewayCurrency);

$payment = Payment::gateway('stripe')->create([
    'money' => ['amount' => $result['converted_amount'], 'currency' => $gatewayCurrency],
    // ...
]);
```
