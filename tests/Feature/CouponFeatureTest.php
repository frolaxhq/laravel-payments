<?php

use Frolax\Payment\Models\Coupon;
use Frolax\Payment\Models\CouponUsage;

// -------------------------------------------------------
// Coupon CRUD & Validation (Database)
// -------------------------------------------------------

test('coupon is created and persisted', function () {
    $coupon = Coupon::create([
        'code' => 'SAVE20',
        'type' => 'percent',
        'value' => 20,
        'is_active' => true,
        'max_uses' => 100,
        'min_spend' => 50.00,
        'max_uses_per_customer' => 2,
        'expires_at' => now()->addMonth(),
    ]);

    expect($coupon->exists)->toBeTrue();

    $found = Coupon::where('code', 'SAVE20')->first();
    expect($found)->not->toBeNull();
    expect($found->type)->toBe('percent');
    expect((float) $found->value)->toBe(20.0);
});

test('coupon tracks usage in database', function () {
    $coupon = Coupon::create([
        'code' => 'TRACK10',
        'type' => 'fixed',
        'value' => 10,
        'is_active' => true,
        'max_uses' => 5,
        'used_count' => 0,
    ]);

    CouponUsage::create([
        'coupon_id' => $coupon->id,
        'payment_id' => 'PAY-001',
        'customer_email' => 'cust@example.com',
        'discount_amount' => 10.00,
        'used_at' => now(),
    ]);

    $coupon->increment('used_count');

    expect($coupon->fresh()->used_count)->toBe(1);
    expect($coupon->usages()->count())->toBe(1);
});

test('coupon scope finds active non-expired coupons', function () {
    Coupon::create([
        'code' => 'ACTIVE1',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'expires_at' => now()->addWeek(),
    ]);

    Coupon::create([
        'code' => 'EXPIRED1',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    Coupon::create([
        'code' => 'INACTIVE1',
        'type' => 'percent',
        'value' => 10,
        'is_active' => false,
    ]);

    $active = Coupon::where('is_active', true)
        ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
        ->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->code)->toBe('ACTIVE1');
});

test('coupon per-customer limit check', function () {
    $coupon = Coupon::create([
        'code' => 'PERLIMIT',
        'type' => 'fixed',
        'value' => 5,
        'is_active' => true,
        'max_uses_per_customer' => 1,
    ]);

    CouponUsage::create([
        'coupon_id' => $coupon->id,
        'payment_id' => 'PAY-001',
        'customer_email' => 'cust-001@example.com',
        'discount_amount' => 5.00,
        'used_at' => now(),
    ]);

    $customerUses = CouponUsage::where('coupon_id', $coupon->id)
        ->where('customer_email', 'cust-001@example.com')
        ->count();
    expect($customerUses >= $coupon->max_uses_per_customer)->toBeTrue();

    $otherUses = CouponUsage::where('coupon_id', $coupon->id)
        ->where('customer_email', 'cust-002@example.com')
        ->count();
    expect($otherUses < $coupon->max_uses_per_customer)->toBeTrue();
});
