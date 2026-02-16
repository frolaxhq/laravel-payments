<?php

use Frolax\Payment\Models\PaymentMethod;
use Frolax\Payment\Models\PaymentLink;
use Frolax\Payment\Models\Subscription;
use Frolax\Payment\Enums\SubscriptionStatus;

// -------------------------------------------------------
// Subscription Model
// -------------------------------------------------------

test('Subscription detects on trial', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(7),
    ]);

    expect($sub->onTrial())->toBeTrue();
});

test('Subscription detects expired trial', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($sub->onTrial())->toBeFalse();
});

test('Subscription detects on grace period', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Cancelled,
        'ends_at' => now()->addDays(5),
    ]);

    expect($sub->onGracePeriod())->toBeTrue();
});

test('Subscription detects active status', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Active,
        'ends_at' => null,
    ]);

    expect($sub->isActive())->toBeTrue();
});

test('Subscription detects paused status', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Paused,
    ]);

    expect($sub->isPaused())->toBeTrue();
});

test('Subscription detects cancelled status', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::Cancelled,
        'ends_at' => now()->subDay(),
    ]);

    expect($sub->isCancelled())->toBeTrue();
});

test('Subscription detects past due status', function () {
    $sub = new Subscription([
        'status' => SubscriptionStatus::PastDue,
    ]);

    expect($sub->isPastDue())->toBeTrue();
});

// -------------------------------------------------------
// PaymentMethod Model
// -------------------------------------------------------

test('PaymentMethod detects expired card', function () {
    $pm = new PaymentMethod([
        'token' => 'tok_xxx',
        'expires_at' => now()->subMonth(),
    ]);

    expect($pm->isExpired())->toBeTrue();
});

test('PaymentMethod detects non-expired card', function () {
    $pm = new PaymentMethod([
        'token' => 'tok_xxx',
        'expires_at' => now()->addYear(),
    ]);

    expect($pm->isExpired())->toBeFalse();
});

test('PaymentMethod with no expiry is not expired', function () {
    $pm = new PaymentMethod([
        'token' => 'tok_xxx',
        'expires_at' => null,
    ]);

    expect($pm->isExpired())->toBeFalse();
});

// -------------------------------------------------------
// PaymentLink Model
// -------------------------------------------------------

test('PaymentLink detects expired link', function () {
    $link = new PaymentLink([
        'slug' => 'test-link',
        'expires_at' => now()->subDay(),
        'is_active' => true,
    ]);

    expect($link->isExpired())->toBeTrue();
    expect($link->isUsable())->toBeFalse();
});

test('PaymentLink detects usable link', function () {
    $link = new PaymentLink([
        'slug' => 'usable',
        'expires_at' => now()->addDay(),
        'is_active' => true,
        'is_single_use' => false,
        'used_at' => null,
    ]);

    expect($link->isUsable())->toBeTrue();
});

test('PaymentLink detects used single-use link', function () {
    $link = new PaymentLink([
        'slug' => 'used',
        'is_active' => true,
        'is_single_use' => true,
        'used_at' => now(),
    ]);

    expect($link->isUsed())->toBeTrue();
    expect($link->isUsable())->toBeFalse();
});

test('PaymentLink generates URL', function () {
    $link = new PaymentLink([
        'slug' => 'abc123',
    ]);

    expect($link->getUrl())->toContain('/payments/link/abc123');
});
