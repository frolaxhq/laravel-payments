<?php

use Frolax\Payment\Models\Coupon;
use Frolax\Payment\Models\CouponUsage;

// -------------------------------------------------------
// Coupon Validation
// -------------------------------------------------------

test('Coupon validates when active and not expired', function () {
    $coupon = new Coupon([
        'code' => 'SAVE10',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'expires_at' => null,
        'max_uses' => null,
        'used_count' => 0,
    ]);

    expect($coupon->isValid())->toBeTrue();
});

test('Coupon rejects when inactive', function () {
    $coupon = new Coupon([
        'code' => 'INACTIVE',
        'type' => 'percent',
        'value' => 10,
        'is_active' => false,
        'used_count' => 0,
    ]);

    expect($coupon->isValid())->toBeFalse();
});

test('Coupon rejects when expired', function () {
    $coupon = new Coupon([
        'code' => 'EXPIRED',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'expires_at' => now()->subDay(),
        'used_count' => 0,
    ]);

    expect($coupon->isValid())->toBeFalse();
});

test('Coupon rejects when max uses reached', function () {
    $coupon = new Coupon([
        'code' => 'MAXED',
        'type' => 'fixed',
        'value' => 20,
        'is_active' => true,
        'max_uses' => 5,
        'used_count' => 5,
    ]);

    expect($coupon->isValid())->toBeFalse();
});

test('Coupon validates minimum spend', function () {
    $coupon = new Coupon([
        'code' => 'MIN50',
        'type' => 'percent',
        'value' => 15,
        'is_active' => true,
        'min_spend' => 50.00,
        'used_count' => 0,
    ]);

    expect($coupon->isValid(30.00))->toBeFalse();
    expect($coupon->isValid(75.00))->toBeTrue();
});

// -------------------------------------------------------
// Coupon Calculation
// -------------------------------------------------------

test('Coupon calculates percent discount', function () {
    $coupon = new Coupon(['type' => 'percent', 'value' => 20]);
    expect($coupon->calculate(100))->toBe(20.00);
    expect($coupon->calculate(49.99))->toBe(10.00);
});

test('Coupon calculates fixed discount', function () {
    $coupon = new Coupon(['type' => 'fixed', 'value' => 15]);
    expect($coupon->calculate(100))->toBe(15.00);
});

test('Coupon fixed discount does not exceed order amount', function () {
    $coupon = new Coupon(['type' => 'fixed', 'value' => 50]);
    expect($coupon->calculate(30))->toBe(30.00);
});
