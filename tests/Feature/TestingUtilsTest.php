<?php

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Data\RefundPayload;
use Frolax\Payment\Data\StatusPayload;
use Frolax\Payment\Data\SubscriptionPayload;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Facades\Payment;
use Frolax\Payment\Testing\FakeDriver;
use Illuminate\Http\Request;

test('fake driver handles create', function () {
    $driver = new FakeDriver;
    $payload = Payload::fromArray(['idempotency_key' => '1', 'order' => ['id' => '1'], 'money' => ['amount' => 10, 'currency' => 'USD']]);
    $cred = new Credentials('fake', 'test', []);

    $driver->willSucceed();
    $result = $driver->create($payload, $cred);
    expect($result->status)->toBe(PaymentStatus::Completed);
    expect($result->gatewayReference)->toStartWith('FAKE-REF');

    $driver->alwaysFail();
    $result2 = $driver->create($payload, $cred);
    expect($result2->status)->toBe(PaymentStatus::Failed);
});

test('fake driver handles verify', function () {
    $driver = new FakeDriver;
    $cred = new Credentials('fake', 'test', []);

    $req = Request::create('/?status=success&reference=ref1');
    $driver->willSucceed();
    $result = $driver->verify($req, $cred);
    expect($result->status)->toBe(PaymentStatus::Completed);

    $req2 = Request::create('/?status=failed');
    $driver->willFail();
    $result2 = $driver->verify($req2, $cred);
    expect($result2->status)->toBe(PaymentStatus::Failed);
});

test('fake driver handles status', function () {
    $driver = new FakeDriver;
    $cred = new Credentials('fake', 'test', []);

    $payload = StatusPayload::fromArray(['payment_id' => '1', 'gateway_reference' => 'ref1']);
    $driver->willSucceed();
    $result = $driver->status($payload, $cred);
    expect($result->status)->toBe(PaymentStatus::Completed);
});

test('fake driver handles subscriptions', function () {
    $driver = new FakeDriver;
    $cred = new Credentials('fake', 'test', []);
    $payload = SubscriptionPayload::fromArray([
        'idempotency_key' => '1', 'plan' => ['id' => '1', 'name' => 'n', 'interval' => 'monthly', 'interval_count' => 1, 'money' => ['amount' => 10, 'currency' => 'USD']],
    ]);

    $driver->willSucceed();
    $res = $driver->createSubscription($payload, $cred);
    expect($res->status)->toBe(PaymentStatus::Completed);

    $driver->willSucceed();
    $res2 = $driver->updateSubscription('sub1', [], $cred);
    expect($res2->status)->toBe(PaymentStatus::Completed);

    $driver->willSucceed();
    $res3 = $driver->pauseSubscription('sub1', $cred);
    expect($res3->status)->toBe(PaymentStatus::Completed);

    $driver->willSucceed();
    $res4 = $driver->resumeSubscription('sub1', $cred);
    expect($res4->status)->toBe(PaymentStatus::Completed);

    $driver->willSucceed();
    $res5 = $driver->cancelSubscription('sub1', $cred);
    expect($res5->status)->toBe(PaymentStatus::Completed);
});

test('fake driver handles refunds', function () {
    $driver = new FakeDriver;
    $cred = new Credentials('fake', 'test', []);
    $payload = RefundPayload::fromArray(['payment_id' => '1', 'money' => ['amount' => 10, 'currency' => 'USD']]);

    $driver->willSucceed();
    $res = $driver->refund($payload, $cred);
    expect($res->status)->toBe(PaymentStatus::Completed);
});

test('payment fake asserts charged', function () {
    Payment::fake();

    Payment::gateway('stripe')->charge([
        'idempotency_key' => '1', 'order' => ['id' => '1'], 'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);

    $fake = Payment::getFacadeRoot();
    $fake->assertCharged(function ($data) {
        return $data['order']['id'] === '1';
    });

    $fake->assertGatewayNotUsed('paypal');
});

test('payment fake asserts refunded', function () {
    Payment::fake();

    Payment::gateway('stripe')->refund([
        'payment_id' => 'p1', 'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);

    $fake = Payment::getFacadeRoot();
    $fake->assertRefunded(function ($data) {
        return $data['payment_id'] === 'p1';
    });
});

test('payment fake asserts subscribed', function () {
    Payment::fake();

    Payment::gateway('stripe')->subscribe([
        'plan' => ['id' => 'p1', 'name' => 'n', 'interval' => 'monthly', 'interval_count' => 1, 'money' => ['amount' => 10, 'currency' => 'USD']],
    ]);

    $fake = Payment::getFacadeRoot();
    $fake->assertSubscribed(function ($data) {
        return $data['plan']['id'] === 'p1';
    });
});
