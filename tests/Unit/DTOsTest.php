<?php

use Frolax\Payment\Data\Address;
use Frolax\Payment\Data\ContextDTO;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\Customer;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\OrderItemDTO;
use Frolax\Payment\Data\PlanDTO;
use Frolax\Payment\Data\StatusPayload;
use Frolax\Payment\Data\SubscriptionPayload;
use Frolax\Payment\Enums\PaymentStatus;

test('address DTO can be instantiated and arrayified', function () {
    $dto = Address::fromArray(['line1' => 'L1', 'line2' => 'L2', 'city' => 'City', 'state' => 'State', 'postal_code' => '12345', 'country' => 'US']);
    expect($dto->toArray())->toBe(['line1' => 'L1', 'line2' => 'L2', 'city' => 'City', 'state' => 'State', 'postal_code' => '12345', 'country' => 'US']);
});

test('canonical status payload works', function () {
    $dto = StatusPayload::fromArray(['payment_id' => 'pay_1', 'gateway_reference' => 'ref_1', 'extra' => ['ext' => 'ra']]);
    expect($dto->toArray())->toBe(['payment_id' => 'pay_1', 'gateway_reference' => 'ref_1', 'extra' => ['ext' => 'ra']]);
});

test('canonical subscription payload works', function () {
    $data = [
        'idempotency_key' => 'id_1',
        'plan' => [
            'id' => 'plan_1', 'name' => 'P', 'interval' => 'monthly', 'interval_count' => 1,
            'money' => ['amount' => 10, 'currency' => 'USD'],
        ],
        'customer' => ['name' => 'n', 'email' => 'e@e.com'],
        'trial_days' => 7,
        'quantity' => 2,
    ];

    $dto = SubscriptionPayload::fromArray($data);

    $arr = $dto->toArray();
    expect($arr['plan']['id'])->toBe('plan_1');
    expect($arr['customer']['email'])->toBe('e@e.com');
    expect($arr['trial_days'])->toBe(7);
});

test('canonical subscription payload throws without plan', function () {
    SubscriptionPayload::fromArray([]);
})->throws(Exception::class);

test('context DTO works', function () {
    $dto = ContextDTO::fromArray(['ip' => '127', 'user_agent' => 'UA', 'locale' => 'en']);
    expect($dto->toArray())->toBe(['ip' => '127', 'user_agent' => 'UA', 'locale' => 'en']);
});

test('credentials DTO array works', function () {
    $dto = Credentials::fromArray(['gateway' => 'gw', 'profile' => 'prof', 'credentials' => ['k' => 'v'], 'tenant_id' => 'ten1', 'label' => 'lab1']);
    expect($dto->toArray())->toBe(['gateway' => 'gw', 'profile' => 'prof', 'credentials' => ['k' => 'v'], 'tenant_id' => 'ten1', 'label' => 'lab1']);
});

test('customer DTO works', function () {
    $dto = Customer::fromArray(['name' => 'n', 'email' => 'e', 'phone' => 'p', 'address' => ['line1' => 'l1']]);
    $arr = $dto->toArray();
    expect($arr['address']['line1'])->toBe('l1');
});

test('gateway result getters work', function () {
    $dto = new GatewayResult(
        PaymentStatus::Completed,
        'ref',
        'url',
        ['res'],
        'err',
        'err_code',
        ['m']
    );
    expect($dto->toArray())->toBe([
        'status' => 'completed',
        'gateway_reference' => 'ref',
        'redirect_url' => 'url',
        'gateway_response' => ['res'],
        'error_message' => 'err',
        'error_code' => 'err_code',
        'metadata' => ['m'],
    ]);
    expect($dto->isSuccessful())->toBeTrue();
    expect($dto->isPending())->toBeFalse();
    expect($dto->requiresRedirect())->toBeTrue();

    $dto2 = new GatewayResult(PaymentStatus::Pending);
    expect($dto2->isSuccessful())->toBeFalse();
    expect($dto2->isPending())->toBeTrue();
    expect($dto2->requiresRedirect())->toBeFalse();
});

test('order item DTO works', function () {
    $dto = OrderItemDTO::fromArray(['name' => 'In', 'quantity' => 1, 'unit_price' => 10, 'sku' => 'sku']);
    $arr = $dto->toArray();
    expect($arr['name'])->toBe('In');
});

test('plan DTO throws without money', function () {
    PlanDTO::fromArray(['id' => '1']);
})->throws(Exception::class);
