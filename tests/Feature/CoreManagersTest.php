<?php

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Contracts\SupportsStatusQuery;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\PaymentRefund;
use Frolax\Payment\Models\Subscription;
use Frolax\Payment\Payment;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\RefundManager;
use Frolax\Payment\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->registry = Mockery::mock(GatewayRegistry::class);
    $this->credentialsRepo = Mockery::mock(CredentialsRepositoryContract::class);
    $this->logger = Mockery::mock(PaymentLoggerContract::class)->makePartial();
    $this->logger->shouldReceive('info', 'error', 'warning')->andReturnNull();

    config()->set('payments.default', 'fake');
    config()->set('payments.profile', 'default');
    config()->set('payments.persistence.enabled', true);
    config()->set('payments.persistence.payments', true);
    config()->set('payments.persistence.attempts', true);
    config()->set('payments.persistence.refunds', true);
    $this->config = new PaymentConfig;

    app()->instance(GatewayRegistry::class, $this->registry);
    app()->instance(CredentialsRepositoryContract::class, $this->credentialsRepo);
    app()->instance(PaymentLoggerContract::class, $this->logger);
    app()->instance(PaymentConfig::class, $this->config);

    $this->credentials = new Credentials('fake', 'default', []);
});

test('payment charge runs pipeline and returns result', function () {
    config()->set('payments.persistence.payments', false);
    config()->set('payments.persistence.attempts', false);
    $this->config = new PaymentConfig;
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $expectedResult = new GatewayResult(PaymentStatus::Completed);
    $driver->shouldReceive('create')->andReturn($expectedResult);

    $result = $payment->gateway('fake')->charge([
        'idempotency_key' => 'idk_1',
        'order' => ['id' => '1', 'description' => 'o', 'items' => []],
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);

    expect($result)->toBe($expectedResult);
});

test('payment create is alias for charge', function () {
    config()->set('payments.persistence.payments', false);
    config()->set('payments.persistence.attempts', false);
    $this->config = new PaymentConfig;
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $expectedResult = new GatewayResult(PaymentStatus::Completed);
    $driver->shouldReceive('create')->andReturn($expectedResult);

    $result = $payment->gateway('fake')->create([
        'idempotency_key' => 'idk_1',
        'order' => ['id' => '1', 'description' => 'o', 'items' => []],
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);

    expect($result)->toBe($expectedResult);
});

test('payment verifyFromRequest updates status and dispatches event on success', function () {
    Event::fake();
    config()->set('payments.persistence.payments', true);
    $this->config = new PaymentConfig;
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    PaymentModel::create([
        'id' => 'pm_1',
        'order_id' => '1',
        'gateway_name' => 'fake',
        'gateway_reference' => 'ref_1',
        'status' => PaymentStatus::Pending,
        'amount' => 10,
        'currency' => 'USD',
    ]);

    $request = Request::create('/verify');
    $expectedResult = new GatewayResult(PaymentStatus::Completed, 'ref_1');
    $driver->shouldReceive('verify')->with($request, $this->credentials)->andReturn($expectedResult);

    $result = $payment->gateway('fake')->verifyFromRequest($request);

    expect($result->status)->toBe(PaymentStatus::Completed);
    expect(PaymentModel::first()->status)->toBe(PaymentStatus::Completed);
    Event::assertDispatched(\Frolax\Payment\Events\PaymentVerified::class);
});

test('payment verifyFromRequest handles failure', function () {
    Event::fake();
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $request = Request::create('/verify');
    $expectedResult = new GatewayResult(PaymentStatus::Failed, 'ref_1', errorMessage: 'err');
    $driver->shouldReceive('verify')->with($request, $this->credentials)->andReturn($expectedResult);

    $result = $payment->gateway('fake')->verifyFromRequest($request);

    expect($result->status)->toBe(PaymentStatus::Failed);
    Event::assertNotDispatched(\Frolax\Payment\Events\PaymentVerified::class);
});

test('payment status throws if unsupported', function () {
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);

    $payment->gateway('fake')->status(['payment_id' => '1', 'gateway_reference' => 'ref_1']);
})->throws(UnsupportedCapabilityException::class);

test('payment status works if supported', function () {
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsStatusQuery::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $result = new GatewayResult(PaymentStatus::Completed);
    $driver->shouldReceive('status')->andReturn($result);

    expect($payment->gateway('fake')->status(['payment_id' => '1', 'gateway_reference' => 'ref_1']))
        ->toBe($result);
});

test('payment static registry methods forward to registry', function () {
    $this->registry->shouldReceive('all')->andReturn(['fake']);
    $this->registry->shouldReceive('supporting')->with('cap')->andReturn(['fake' => static::class]);

    expect(Payment::gateways())->toBe(['fake']);
    expect(Payment::gatewaysThatSupport('cap'))->toBe(['fake' => static::class]);
});

test('refund manager throws if unsupported', function () {
    $manager = new RefundManager($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class);
    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);

    $manager->gateway('fake')->refund([
        'payment_id' => '1',
        'gateway_reference' => 'ref_1',
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);
})->throws(UnsupportedCapabilityException::class);

test('refund manager processes refund', function () {
    Event::fake();
    config()->set('payments.persistence.refunds', true);
    $this->config = new PaymentConfig;
    $manager = new RefundManager($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsRefund::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $result = new GatewayResult(PaymentStatus::Completed, gatewayReference: 'ref_r_1');
    $driver->shouldReceive('refund')->andReturn($result);

    $manager->gateway('fake')->refund([
        'payment_id' => 'p_1',
        'gateway_reference' => 'ref_1',
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ]);

    $refund = PaymentRefund::first();
    expect($refund->payment_id)->toBe('p_1');
    expect($refund->status)->toBe(\Frolax\Payment\Enums\RefundStatus::Completed);

    Event::assertDispatched(\Frolax\Payment\Events\PaymentRefundRequested::class);
    Event::assertDispatched(\Frolax\Payment\Events\PaymentRefunded::class);
});

test('refund manager fails and logs on exception', function () {
    Event::fake();
    config()->set('payments.persistence.refunds', true);
    $this->config = new PaymentConfig;
    $manager = new RefundManager($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsRefund::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $driver->shouldReceive('refund')->andThrow(new \Exception('refund fail'));

    try {
        $manager->gateway('fake')->refund([
            'payment_id' => 'p_1',
            'gateway_reference' => 'ref_1',
            'money' => ['amount' => 10, 'currency' => 'USD'],
        ]);
        $this->fail();
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('refund fail');
    }

    $refund = PaymentRefund::first();
    expect($refund->status)->toBe(\Frolax\Payment\Enums\RefundStatus::Failed);
});

test('subscription manager delegates to driver', function () {
    Event::fake();
    $manager = new SubscriptionManager($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsRecurring::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    $this->credentialsRepo->shouldReceive('get')->with('fake', 'default', [])->andReturn($this->credentials);

    $result = new GatewayResult(PaymentStatus::Completed, 'sub_1');
    $driver->shouldReceive('createSubscription')->andReturn($result);

    $res = $manager->gateway('fake')->create([
        'plan' => ['id' => 'p1', 'name' => 'n', 'interval' => 'monthly', 'interval_count' => 1, 'money' => ['amount' => 10, 'currency' => 'USD']],
        'quantity' => 1,
        'trial_days' => 7,
    ]);

    expect($res->gatewayReference)->toBe('sub_1');

    $sub = Subscription::first();
    expect($sub->status)->toBe(\Frolax\Payment\Enums\SubscriptionStatus::Trialing);

    $driver->shouldReceive('pauseSubscription')->with('sub_1', $this->credentials)->andReturn($result);
    $manager->gateway('fake')->pause($sub->id);
    expect($sub->fresh()->status)->toBe(\Frolax\Payment\Enums\SubscriptionStatus::Paused);

    $driver->shouldReceive('resumeSubscription')->with('sub_1', $this->credentials)->andReturn($result);
    $manager->gateway('fake')->resume($sub->id);
    expect($sub->fresh()->status)->toBe(\Frolax\Payment\Enums\SubscriptionStatus::Active);

    $driver->shouldReceive('updateSubscription')->with('sub_1', ['quantity' => 2], $this->credentials)->andReturn($result);
    $manager->gateway('fake')->update($sub->id, ['quantity' => 2]);
    expect($sub->fresh()->quantity)->toBe(2);

    $driver->shouldReceive('cancelSubscription')->with('sub_1', $this->credentials)->andReturn($result);
    $manager->gateway('fake')->cancel($sub->id, true);
    expect($sub->fresh()->status)->toBe(\Frolax\Payment\Enums\SubscriptionStatus::Cancelled);
});

test('payment delegates sub and refund', function () {
    config()->set('payments.persistence.refunds', false);
    $this->config = new PaymentConfig;
    $payment = new Payment($this->registry, $this->credentialsRepo, $this->logger, $this->config);
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsRecurring::class.','.SupportsRefund::class);

    $this->registry->shouldReceive('resolve')->with('fake')->andReturn($driver);
    // When usingCredentials is used, credentialsRepo is NOT called! The HasGatewayContext uses the provided ones directly.

    $result = new GatewayResult(PaymentStatus::Completed);
    $driver->shouldReceive('createSubscription')->andReturn($result);
    $driver->shouldReceive('refund')->andReturn($result);

    $payment->gateway('fake')->usingCredentials(['k' => 'v'])->subscribe([
        'plan' => ['id' => 'p1', 'name' => 'n', 'interval' => 'monthly', 'interval_count' => 1, 'money' => ['amount' => 10, 'currency' => 'USD']],
        'quantity' => 1,
    ]);

    $payment->gateway('fake')->usingCredentials(['k' => 'v'])->refund([
        'payment_id' => '1', 'gateway_reference' => 'ref', 'money' => ['amount' => 1, 'currency' => 'USD'],
    ]);

    expect(true)->toBeTrue();
});
