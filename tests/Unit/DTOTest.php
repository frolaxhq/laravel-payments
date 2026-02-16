<?php

use Frolax\Payment\DTOs\AddressDTO;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CanonicalRefundPayload;
use Frolax\Payment\DTOs\CanonicalStatusPayload;
use Frolax\Payment\DTOs\ContextDTO;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\CustomerDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\DTOs\MoneyDTO;
use Frolax\Payment\DTOs\OrderDTO;
use Frolax\Payment\DTOs\OrderItemDTO;
use Frolax\Payment\DTOs\UrlsDTO;
use Frolax\Payment\Enums\PaymentStatus;

// -------------------------------------------------------
// MoneyDTO
// -------------------------------------------------------

test('MoneyDTO creates from array', function () {
    $money = MoneyDTO::fromArray(['amount' => 99.99, 'currency' => 'usd']);
    expect($money->amount)->toBe(99.99);
    expect($money->currency)->toBe('USD');
});

test('MoneyDTO rejects negative amount', function () {
    MoneyDTO::fromArray(['amount' => -1, 'currency' => 'USD']);
})->throws(InvalidArgumentException::class);

test('MoneyDTO requires amount', function () {
    MoneyDTO::fromArray(['currency' => 'USD']);
})->throws(InvalidArgumentException::class);

// -------------------------------------------------------
// AddressDTO
// -------------------------------------------------------

test('AddressDTO creates from array', function () {
    $address = AddressDTO::fromArray([
        'line1' => '123 Main St',
        'city' => 'Dhaka',
        'country' => 'BD',
    ]);
    expect($address->line1)->toBe('123 Main St');
    expect($address->city)->toBe('Dhaka');
    expect($address->country)->toBe('BD');
});

test('AddressDTO returns null for empty array', function () {
    expect(AddressDTO::fromArray([]))->toBeNull();
    expect(AddressDTO::fromArray(null))->toBeNull();
});

// -------------------------------------------------------
// CustomerDTO
// -------------------------------------------------------

test('CustomerDTO creates with nested address', function () {
    $customer = CustomerDTO::fromArray([
        'name' => 'John',
        'email' => 'john@example.com',
        'address' => ['city' => 'NYC'],
    ]);
    expect($customer->name)->toBe('John');
    expect($customer->address->city)->toBe('NYC');
});

// -------------------------------------------------------
// OrderDTO
// -------------------------------------------------------

test('OrderDTO creates with items', function () {
    $order = OrderDTO::fromArray([
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
// CanonicalPayload
// -------------------------------------------------------

test('CanonicalPayload creates from full array', function () {
    $payload = CanonicalPayload::fromArray([
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

test('CanonicalPayload auto-generates idempotency key', function () {
    config()->set('payments.idempotency.auto_generate', true);

    $payload = CanonicalPayload::fromArray([
        'order' => ['id' => 'ORD-002'],
        'money' => ['amount' => 50, 'currency' => 'BDT'],
    ]);

    expect($payload->idempotencyKey)->not->toBeEmpty();
});

test('CanonicalPayload flattens to dot notation', function () {
    $payload = CanonicalPayload::fromArray([
        'idempotency_key' => 'key-001',
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    $dot = $payload->toDotArray();
    expect($dot)->toHaveKey('order.id', 'ORD-001');
    expect($dot)->toHaveKey('money.amount', 100);
    expect($dot)->toHaveKey('money.currency', 'USD');
});

test('CanonicalPayload requires order', function () {
    CanonicalPayload::fromArray([
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);
})->throws(InvalidArgumentException::class);

// -------------------------------------------------------
// CanonicalRefundPayload
// -------------------------------------------------------

test('CanonicalRefundPayload creates from array', function () {
    $payload = CanonicalRefundPayload::fromArray([
        'payment_id' => 'PAY-001',
        'money' => ['amount' => 50, 'currency' => 'USD'],
        'reason' => 'Customer request',
    ]);

    expect($payload->paymentId)->toBe('PAY-001');
    expect($payload->money->amount)->toBe(50);
    expect($payload->reason)->toBe('Customer request');
});

// -------------------------------------------------------
// CanonicalStatusPayload
// -------------------------------------------------------

test('CanonicalStatusPayload creates from array', function () {
    $payload = CanonicalStatusPayload::fromArray([
        'payment_id' => 'PAY-001',
        'gateway_reference' => 'GW-REF-001',
    ]);

    expect($payload->paymentId)->toBe('PAY-001');
    expect($payload->gatewayReference)->toBe('GW-REF-001');
});

// -------------------------------------------------------
// CredentialsDTO
// -------------------------------------------------------

test('CredentialsDTO masks credentials in safe array', function () {
    $creds = new CredentialsDTO(
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
