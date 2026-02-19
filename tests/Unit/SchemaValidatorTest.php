<?php

use Frolax\Payment\Services\SchemaValidator;

test('SchemaValidator passes with valid payload', function () {
    $validator = new SchemaValidator;

    expect($validator->passes([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]))->toBeTrue();
});

test('SchemaValidator fails without order id', function () {
    $validator = new SchemaValidator;

    $errors = $validator->validate([
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    expect($errors)->not->toBeEmpty();
    expect($errors[0]['field'])->toBe('order.id');
});

test('SchemaValidator fails without money amount', function () {
    $validator = new SchemaValidator;

    $errors = $validator->validate([
        'order' => ['id' => 'ORD-001'],
        'money' => ['currency' => 'USD'],
    ]);

    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->pluck('field'))->toContain('money.amount');
});

test('SchemaValidator rejects negative amount', function () {
    $validator = new SchemaValidator;

    $errors = $validator->validate([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => -5, 'currency' => 'USD'],
    ]);

    expect(collect($errors)->pluck('rule'))->toContain('positive');
});

test('SchemaValidator rejects invalid currency format', function () {
    $validator = new SchemaValidator;

    $errors = $validator->validate([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'U'],
    ]);

    expect(collect($errors)->pluck('rule'))->toContain('currency_format');
});

test('SchemaValidator validates gateway-specific rules', function () {
    $validator = new SchemaValidator;
    $validator->forGateway('stripe', [
        'customer.email' => ['required'],
    ]);

    $errors = $validator->validate([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ], 'stripe');

    expect(collect($errors)->pluck('field'))->toContain('customer.email');
});

test('SchemaValidator passes with gateway fields present', function () {
    $validator = new SchemaValidator;
    $validator->forGateway('stripe', [
        'customer.email' => ['required'],
    ]);

    expect($validator->passes([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
        'customer' => ['email' => 'test@test.com'],
    ], 'stripe'))->toBeTrue();
});
