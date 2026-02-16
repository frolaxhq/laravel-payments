<?php

use Frolax\Payment\Models\PaymentLink;

// -------------------------------------------------------
// PaymentLink CRUD
// -------------------------------------------------------

test('payment link is created with auto-generated slug', function () {
    $link = PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Invoice #1234',
        'amount' => 500.00,
        'currency' => 'USD',
        'is_active' => true,
    ]);

    expect($link->exists)->toBeTrue();
    expect($link->slug)->not->toBeNull();
    expect(strlen($link->slug))->toBe(16);
    expect($link->getUrl())->toContain($link->slug);
});

test('payment link slug is unique', function () {
    $link1 = PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Link 1',
        'amount' => 10.00,
        'currency' => 'USD',
    ]);

    $link2 = PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Link 2',
        'amount' => 20.00,
        'currency' => 'USD',
    ]);

    expect($link1->slug)->not->toBe($link2->slug);
});

test('payment link mark used', function () {
    $link = PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Single Use',
        'amount' => 100.00,
        'currency' => 'USD',
        'is_active' => true,
        'is_single_use' => true,
    ]);

    expect($link->isUsable())->toBeTrue();
    expect($link->isUsed())->toBeFalse();

    $link->markUsed();
    $link->refresh();

    expect($link->isUsed())->toBeTrue();
    expect($link->isUsable())->toBeFalse();
});

test('payment link active scope filters correctly', function () {
    PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Active Link',
        'amount' => 50.00,
        'currency' => 'USD',
        'is_active' => true,
        'expires_at' => now()->addWeek(),
    ]);

    PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Expired Link',
        'amount' => 50.00,
        'currency' => 'USD',
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Disabled Link',
        'amount' => 50.00,
        'currency' => 'USD',
        'is_active' => false,
    ]);

    $active = PaymentLink::active()->get();
    expect($active)->toHaveCount(1);
    expect($active->first()->title)->toBe('Active Link');
});

test('payment link found by slug', function () {
    $link = PaymentLink::create([
        'gateway_name' => 'stripe',
        'title' => 'Slug Test',
        'amount' => 75.00,
        'currency' => 'USD',
    ]);

    $found = PaymentLink::bySlug($link->slug)->first();
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($link->id);
});
