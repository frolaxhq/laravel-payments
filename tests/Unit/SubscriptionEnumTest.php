<?php

use Frolax\Payment\Enums\BillingInterval;
use Frolax\Payment\Enums\SubscriptionStatus;

// -------------------------------------------------------
// SubscriptionStatus
// -------------------------------------------------------

test('SubscriptionStatus active statuses are correct', function () {
    expect(SubscriptionStatus::Active->isActive())->toBeTrue();
    expect(SubscriptionStatus::Trialing->isActive())->toBeTrue();
    expect(SubscriptionStatus::Paused->isActive())->toBeFalse();
    expect(SubscriptionStatus::Cancelled->isActive())->toBeFalse();
});

test('SubscriptionStatus terminal statuses are correct', function () {
    expect(SubscriptionStatus::Cancelled->isTerminal())->toBeTrue();
    expect(SubscriptionStatus::Expired->isTerminal())->toBeTrue();
    expect(SubscriptionStatus::Active->isTerminal())->toBeFalse();
});

test('SubscriptionStatus cancellable statuses', function () {
    expect(SubscriptionStatus::Active->canBeCancelled())->toBeTrue();
    expect(SubscriptionStatus::Trialing->canBeCancelled())->toBeTrue();
    expect(SubscriptionStatus::PastDue->canBeCancelled())->toBeTrue();
    expect(SubscriptionStatus::Cancelled->canBeCancelled())->toBeFalse();
});

test('SubscriptionStatus pausable statuses', function () {
    expect(SubscriptionStatus::Active->canBePaused())->toBeTrue();
    expect(SubscriptionStatus::Trialing->canBePaused())->toBeTrue();
    expect(SubscriptionStatus::Paused->canBePaused())->toBeFalse();
});

test('SubscriptionStatus resumable statuses', function () {
    expect(SubscriptionStatus::Paused->canBeResumed())->toBeTrue();
    expect(SubscriptionStatus::Active->canBeResumed())->toBeFalse();
});

// -------------------------------------------------------
// BillingInterval
// -------------------------------------------------------

test('BillingInterval converts to days', function () {
    expect(BillingInterval::Daily->toDays())->toBe(1);
    expect(BillingInterval::Weekly->toDays())->toBe(7);
    expect(BillingInterval::Monthly->toDays())->toBe(30);
    expect(BillingInterval::Quarterly->toDays())->toBe(90);
    expect(BillingInterval::Yearly->toDays())->toBe(365);
    expect(BillingInterval::Custom->toDays())->toBeNull();
});

test('BillingInterval provides human labels', function () {
    expect(BillingInterval::Daily->label())->toBe('day');
    expect(BillingInterval::Monthly->label())->toBe('month');
    expect(BillingInterval::Yearly->label())->toBe('year');
});
