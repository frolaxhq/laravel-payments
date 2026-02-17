# Coupons & Discounts

Apply discounts to payments with full validation and usage tracking.

## Creating Coupons

```php
use Frolax\Payment\Models\Coupon;

$coupon = Coupon::create([
    'code' => 'SAVE20',
    'type' => 'percent',  // 'percent' or 'fixed'
    'value' => 20,
    'is_active' => true,
    'max_uses' => 100,
    'min_spend' => 50.00,
    'max_uses_per_customer' => 1,
    'expires_at' => now()->addMonth(),
]);
```

## Validating & Applying

```php
$coupon = Coupon::where('code', 'SAVE20')->first();

// Validate (checks active, expiry, usage limits, min spend)
if ($coupon->isValid(orderAmount: 75.00)) {
    $discount = $coupon->calculate(75.00);
    // $discount = 15.00 (20% of 75)
}

// With per-customer check
if ($coupon->isValid(75.00, customerId: $userId)) {
    // Also checks per-customer usage limit
}
```

## Discount Types

| Type | Behavior |
|------|----------|
| `percent` | Percentage off the total (e.g., 20% → $15 off $75) |
| `fixed` | Fixed amount off (capped at order total) |

## Usage Tracking

Usage is tracked automatically through `CouponUsage`:

```php
use Frolax\Payment\Models\CouponUsage;

// Record usage
$coupon->recordUsage($paymentId, $customerId);

// Query usage
$usages = $coupon->usages()->count();
```

## Validation Rules

The `isValid()` method checks:
1. **Active status** — `is_active` must be `true`
2. **Expiry** — `expires_at` must be in the future (if set)
3. **Usage limit** — `used_count` must be below `max_uses` (if set)
4. **Minimum spend** — Order amount must meet `min_spend` (if set)
5. **Per-customer limit** — Customer's redemptions below `max_uses_per_customer` (if set)
