<?php

use Frolax\Payment\DTOs\CanonicalSubscriptionPayload;
use Frolax\Payment\DTOs\PlanDTO;
use Frolax\Payment\DTOs\SubscriptionItemDTO;

// -------------------------------------------------------
// PlanDTO
// -------------------------------------------------------

test('PlanDTO creates from array', function () {
    $plan = PlanDTO::fromArray([
        'id' => 'plan_pro',
        'name' => 'Pro Plan',
        'money' => ['amount' => 49.99, 'currency' => 'USD'],
        'interval' => 'monthly',
        'interval_count' => 1,
    ]);

    expect($plan->id)->toBe('plan_pro');
    expect($plan->name)->toBe('Pro Plan');
    expect($plan->money->amount)->toBe(49.99);
    expect($plan->interval)->toBe('monthly');
    expect($plan->intervalCount)->toBe(1);
});

test('PlanDTO supports trial days', function () {
    $plan = PlanDTO::fromArray([
        'id' => 'plan_trial',
        'name' => 'Trial Plan',
        'money' => ['amount' => 29.99, 'currency' => 'USD'],
        'interval' => 'monthly',
        'trial_days' => 14,
    ]);

    expect($plan->trialDays)->toBe(14);
});

test('PlanDTO supports features array', function () {
    $plan = PlanDTO::fromArray([
        'id' => 'plan_features',
        'name' => 'Feature Plan',
        'money' => ['amount' => 99.99, 'currency' => 'USD'],
        'interval' => 'yearly',
        'features' => ['unlimited_api', 'priority_support'],
    ]);

    expect($plan->features)->toBe(['unlimited_api', 'priority_support']);
});

// -------------------------------------------------------
// SubscriptionItemDTO
// -------------------------------------------------------

test('SubscriptionItemDTO creates from array', function () {
    $item = SubscriptionItemDTO::fromArray([
        'product_id' => 'prod_001',
        'name' => 'API Access',
        'quantity' => 5,
        'unit_price' => ['amount' => 10.00, 'currency' => 'USD'],
    ]);

    expect($item->productId)->toBe('prod_001');
    expect($item->name)->toBe('API Access');
    expect($item->quantity)->toBe(5);
    expect($item->unitPrice->amount)->toBe(10.00);
});

test('SubscriptionItemDTO creates without unit price', function () {
    $item = SubscriptionItemDTO::fromArray([
        'product_id' => 'prod_002',
        'name' => 'Addon',
        'quantity' => 1,
    ]);

    expect($item->productId)->toBe('prod_002');
    expect($item->name)->toBe('Addon');
    expect($item->unitPrice)->toBeNull();
});

test('SubscriptionItemDTO converts to array', function () {
    $item = SubscriptionItemDTO::fromArray([
        'product_id' => 'prod_003',
        'name' => 'Seats',
        'quantity' => 3,
    ]);

    $arr = $item->toArray();
    expect($arr['product_id'])->toBe('prod_003');
    expect($arr['name'])->toBe('Seats');
    expect($arr['quantity'])->toBe(3);
});

// -------------------------------------------------------
// CanonicalSubscriptionPayload
// -------------------------------------------------------

test('CanonicalSubscriptionPayload creates from array', function () {
    $payload = CanonicalSubscriptionPayload::fromArray([
        'plan' => [
            'id' => 'plan_pro',
            'name' => 'Pro Plan',
            'money' => ['amount' => 49.99, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
        'customer' => [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ],
        'urls' => [
            'return' => 'https://example.com/return',
        ],
    ]);

    expect($payload->plan->id)->toBe('plan_pro');
    expect($payload->customer->name)->toBe('Jane Doe');
    expect($payload->urls->return)->toBe('https://example.com/return');
});

test('CanonicalSubscriptionPayload supports trial days', function () {
    $payload = CanonicalSubscriptionPayload::fromArray([
        'plan' => [
            'id' => 'plan_trial',
            'name' => 'Trial',
            'money' => ['amount' => 0, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
        'trial_days' => 7,
    ]);

    expect($payload->trialDays)->toBe(7);
});

test('CanonicalSubscriptionPayload supports items', function () {
    $payload = CanonicalSubscriptionPayload::fromArray([
        'plan' => [
            'id' => 'plan_multi',
            'name' => 'Multi',
            'money' => ['amount' => 100, 'currency' => 'USD'],
            'interval' => 'monthly',
        ],
        'items' => [
            ['product_id' => 'seat', 'name' => 'Seat License', 'quantity' => 5],
        ],
    ]);

    expect($payload->items)->toHaveCount(1);
    expect($payload->items[0]->productId)->toBe('seat');
    expect($payload->items[0]->name)->toBe('Seat License');
    expect($payload->items[0]->quantity)->toBe(5);
});

test('CanonicalSubscriptionPayload requires plan', function () {
    CanonicalSubscriptionPayload::fromArray([
        'customer' => ['email' => 'test@test.com'],
    ]);
})->throws(ErrorException::class);
