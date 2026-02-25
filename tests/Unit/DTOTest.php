<?php

use Frolax\Payment\Data\Address;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\Customer;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Money;
use Frolax\Payment\Data\Order;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Data\RefundPayload;
use Frolax\Payment\Data\StatusPayload;
use Frolax\Payment\Enums\PaymentStatus;

// -------------------------------------------------------
// Money
// -------------------------------------------------------

test('Money creates from array', function () {
    $money = Money::fromArray(['amount' => 99.99, 'currency' => 'usd']);
    expect($money->amount)->toBe(99.99);
    expect($money->currency)->toBe('USD');
});

test('Money rejects negative amount', function () {
    Money::fromArray(['amount' => -1, 'currency' => 'USD']);
})->throws(InvalidArgumentException::class);

test('Money requires amount', function () {
    Money::fromArray(['currency' => 'USD']);
})->throws(InvalidArgumentException::class);

// -------------------------------------------------------
// Address
// -------------------------------------------------------

test('Address creates from array', function () {
    $address = Address::fromArray([
        'line1' => '123 Main St',
        'city' => 'Dhaka',
        'country' => 'BD',
    ]);
    expect($address->line1)->toBe('123 Main St');
    expect($address->city)->toBe('Dhaka');
    expect($address->country)->toBe('BD');
});

test('Address returns null for empty array', function () {
    expect(Address::fromArray([]))->toBeNull();
    expect(Address::fromArray(null))->toBeNull();
});

// -------------------------------------------------------
// Customer
// -------------------------------------------------------

test('Customer creates with nested address', function () {
    $customer = Customer::fromArray([
        'name' => 'John',
        'email' => 'john@example.com',
        'address' => ['city' => 'NYC'],
    ]);
    expect($customer->name)->toBe('John');
    expect($customer->address->city)->toBe('NYC');
});

// -------------------------------------------------------
// Order
// -------------------------------------------------------

test('Order creates with items', function () {
    $order = Order::fromArray([
        'id' => 'ORD-001',
        'description' => 'Test',
        'items' => [
            ['name' => 'Widget', 'quantity' => 2, 'unit_price' => 10],
        ],
    ]);
    expect($order->id)->toBe('ORD-001');
    expect($order->items)->toHaveCount(1);
    expect($order->items[0]->name)->toBe('Widget');
});

// -------------------------------------------------------
// Payload
// -------------------------------------------------------

test('Payload creates from full array', function () {
    $payload = Payload::fromArray([
        'idempotency_key' => 'test-key-001',
        'order' => ['id' => 'ORD-001', 'description' => 'Test Order'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
        'customer' => ['name' => 'John', 'email' => 'john@example.com'],
        'urls' => ['return' => 'https://example.com/return'],
        'context' => ['ip' => '127.0.0.1'],
        'metadata' => ['key' => 'value'],
    ]);

    expect($payload->idempotencyKey)->toBe('test-key-001');
    expect($payload->order->id)->toBe('ORD-001');
    expect($payload->money->amount)->toBe(100);
    expect($payload->money->currency)->toBe('USD');
    expect($payload->customer->name)->toBe('John');
    expect($payload->urls->return)->toBe('https://example.com/return');
});

test('Payload auto-generates idempotency key', function () {
    config()->set('payments.idempotency.auto_generate', true);

    $payload = Payload::fromArray([
        'order' => ['id' => 'ORD-002'],
        'money' => ['amount' => 50, 'currency' => 'BDT'],
    ]);

    expect($payload->idempotencyKey)->not->toBeEmpty();
});

test('Payload flattens to dot notation', function () {
    $payload = Payload::fromArray([
        'idempotency_key' => 'key-001',
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    $dot = $payload->toDotArray();
    expect($dot)->toHaveKey('order.id', 'ORD-001');
    expect($dot)->toHaveKey('money.amount', 100);
    expect($dot)->toHaveKey('money.currency', 'USD');
});

test('Payload requires order', function () {
    Payload::fromArray([
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);
})->throws(InvalidArgumentException::class);

// -------------------------------------------------------
// RefundPayload
// -------------------------------------------------------

test('RefundPayload creates from array', function () {
    $payload = RefundPayload::fromArray([
        'payment_id' => 'PAY-001',
        'money' => ['amount' => 50, 'currency' => 'USD'],
        'reason' => 'Customer request',
    ]);

    expect($payload->paymentId)->toBe('PAY-001');
    expect($payload->money->amount)->toBe(50);
    expect($payload->reason)->toBe('Customer request');
});

// -------------------------------------------------------
// StatusPayload
// -------------------------------------------------------

test('StatusPayload creates from array', function () {
    $payload = StatusPayload::fromArray([
        'payment_id' => 'PAY-001',
        'gateway_reference' => 'GW-REF-001',
    ]);

    expect($payload->paymentId)->toBe('PAY-001');
    expect($payload->gatewayReference)->toBe('GW-REF-001');
});

// -------------------------------------------------------
// Credentials
// -------------------------------------------------------

test('Credentials masks credentials in safe array', function () {
    $creds = new Credentials(
        gateway: 'stripe',
        profile: 'test',
        credentials: ['key' => 'sk_test_xxx', 'secret' => 'whsec_xxx'],
    );

    expect($creds->get('key'))->toBe('sk_test_xxx');
    expect($creds->toSafeArray()['credentials'])->toBe('[REDACTED]');
});

// -------------------------------------------------------
// GatewayResult
// -------------------------------------------------------

test('GatewayResult identifies successful status', function () {
    $result = new GatewayResult(status: PaymentStatus::Completed);
    expect($result->isSuccessful())->toBeTrue();
    expect($result->isPending())->toBeFalse();
});

test('GatewayResult identifies redirect', function () {
    $result = new GatewayResult(
        status: PaymentStatus::Pending,
        redirectUrl: 'https://gateway.com/pay',
    );
    expect($result->requiresRedirect())->toBeTrue();
    expect($result->isPending())->toBeTrue();
});
