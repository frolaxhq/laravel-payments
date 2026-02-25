<?php

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Money;
use Frolax\Payment\Data\Order;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Events\PaymentCreated;
use Frolax\Payment\Events\PaymentFailed;
use Frolax\Payment\Models\PaymentAttempt;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;
use Frolax\Payment\Pipeline\Steps\CheckIdempotency;
use Frolax\Payment\Pipeline\Steps\DispatchPaymentEvent;
use Frolax\Payment\Pipeline\Steps\ExecuteGatewayCall;
use Frolax\Payment\Pipeline\Steps\PersistAttempt;
use Frolax\Payment\Pipeline\Steps\PersistPaymentRecord;
use Frolax\Payment\Pipeline\Steps\UpdatePaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTestContext(): PaymentContext
{
    $driver = Mockery::mock(GatewayDriverContract::class);
    $payload = new Payload(
        idempotencyKey: 'idemp_key_1',
        order: new Order('order_1', 'Order 1'),
        money: new Money(100.0, 'USD'),
        urls: new \Frolax\Payment\Data\UrlsDTO('http://example.com/callback')
    );
    $credentials = new Credentials('fake', 'default', ['key' => 'test_key', 'secret' => 'test_secret']);

    return new PaymentContext(
        gateway: 'fake_gateway',
        profile: 'default',
        driver: $driver,
        payload: $payload,
        credentials: $credentials,
        tenantId: 'tenant_1'
    );
}

test('payment context instantiates correctly', function () {
    $context = createTestContext();
    expect($context->gateway)->toBe('fake_gateway');
});

test('check idempotency skips if persistence disabled', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnFalse();

    $step = new CheckIdempotency($config);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBe('next');
});

test('check idempotency returns existing completed payment', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnTrue();

    PaymentModel::create([
        'id' => (string) Str::ulid(),
        'order_id' => 'order_1',
        'gateway_name' => 'fake_gateway',
        'idempotency_key' => 'idemp_key_1',
        'amount' => 100.0,
        'currency' => 'USD',
        'status' => PaymentStatus::Completed,
        'gateway_reference' => 'ref_123',
    ]);

    $step = new CheckIdempotency($config);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBeInstanceOf(PaymentContext::class);
    expect($result->result->status)->toBe(PaymentStatus::Completed);
    expect($result->result->gatewayReference)->toBe('ref_123');
});

test('dispatch event throws and dispatches failed on exception', function () {
    Event::fake();

    $step = new DispatchPaymentEvent;
    $context = createTestContext();
    $context->paymentId = 'pay_1';
    $context->exception = new \Exception('Failed step');

    try {
        $step->handle($context, fn ($ctx) => 'next');
        $this->fail('Exception not re-thrown');
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Failed step');
    }

    Event::assertDispatched(PaymentFailed::class, function ($e) {
        return $e->paymentId === 'pay_1' && $e->errorMessage === 'Failed step';
    });
});

test('dispatch event dispatches created on success', function () {
    Event::fake();

    $step = new DispatchPaymentEvent;
    $context = createTestContext();
    $context->paymentId = 'pay_1';
    $context->result = new GatewayResult(PaymentStatus::Completed, 'ref_1', 'redir_url');

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBe('next');

    Event::assertDispatched(PaymentCreated::class, function ($e) {
        return $e->paymentId === 'pay_1' && $e->redirectUrl === 'redir_url';
    });
});

test('execute gateway call successfully creates payment', function () {
    $logger = Mockery::mock(PaymentLoggerContract::class);
    $logger->shouldReceive('info')->once();

    $step = new ExecuteGatewayCall($logger);
    $context = createTestContext();

    $expectedResult = new GatewayResult(PaymentStatus::Completed);
    $context->driver->shouldReceive('create')->with($context->payload, $context->credentials)->andReturn($expectedResult);

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->attemptNo)->toBe(1);
    expect($result->result)->toBe($expectedResult);
    expect($result->elapsedMs)->toBeGreaterThanOrEqual(0);
});

test('execute gateway call catches exceptions', function () {
    $logger = Mockery::mock(PaymentLoggerContract::class);
    $logger->shouldReceive('info')->once();

    $step = new ExecuteGatewayCall($logger);
    $context = createTestContext();

    $context->driver->shouldReceive('create')->andThrow(new \Exception('Driver err'));

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->exception)->toBeInstanceOf(\Exception::class);
    expect($result->exception->getMessage())->toBe('Driver err');
});

test('persist attempt skips if persistence disabled', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistAttempts')->andReturnFalse();

    $step = new PersistAttempt($config);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBe('next');
});

test('persist attempt saves error attempt', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistAttempts')->andReturnTrue();

    $step = new PersistAttempt($config);
    $context = createTestContext();
    $context->paymentId = 'pay_1';
    $context->exception = new \RuntimeException('test_err', 123);

    $step->handle($context, fn ($ctx) => $ctx);

    $attempt = PaymentAttempt::first();
    expect($attempt->payment_id)->toBe('pay_1');
    expect($attempt->status)->toBe(\Frolax\Payment\Enums\AttemptStatus::Error);
    expect($attempt->errors['message'])->toBe('test_err');
});

test('persist attempt saves success attempt', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistAttempts')->andReturnTrue();

    $step = new PersistAttempt($config);
    $context = createTestContext();
    $context->paymentId = 'pay_1';
    $context->result = new GatewayResult(PaymentStatus::Completed, 'ref_1');

    $step->handle($context, fn ($ctx) => $ctx);

    $attempt = PaymentAttempt::first();
    expect($attempt->payment_id)->toBe('pay_1');
    expect($attempt->status)->toBe(\Frolax\Payment\Enums\AttemptStatus::Succeeded);
});

test('persist payment record creates record', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnTrue();

    $step = new PersistPaymentRecord($config);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->paymentId)->not->toBeNull();
    $payment = PaymentModel::first();
    expect($payment->id)->toBe($result->paymentId);
    expect($payment->order_id)->toBe('order_1');
});

test('update status processes exception correctly', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnTrue();
    $logger = Mockery::mock(PaymentLoggerContract::class);
    $logger->shouldReceive('error')->once();

    $step = new UpdatePaymentStatus($config, $logger);
    $context = createTestContext();
    $context->paymentId = (string) Str::ulid();
    $context->exception = new \Exception('fail2');

    PaymentModel::create([
        'id' => $context->paymentId,
        'order_id' => 'o1',
        'gateway_name' => 'f1',
        'status' => PaymentStatus::Pending,
        'amount' => 10,
        'currency' => 'USD',
    ]);

    $step->handle($context, fn ($ctx) => 'next');

    expect(PaymentModel::first()->status)->toBe(PaymentStatus::Failed);
});

test('update status processes success correctly', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnTrue();
    $logger = Mockery::mock(PaymentLoggerContract::class);
    $logger->shouldReceive('info')->once();

    $step = new UpdatePaymentStatus($config, $logger);
    $context = createTestContext();
    $context->paymentId = (string) Str::ulid();
    $context->result = new GatewayResult(PaymentStatus::Processing, 'ref_ok');

    PaymentModel::create([
        'id' => $context->paymentId,
        'order_id' => 'o1',
        'gateway_name' => 'f1',
        'status' => PaymentStatus::Pending,
        'amount' => 10,
        'currency' => 'USD',
    ]);

    $step->handle($context, fn ($ctx) => 'next');

    $payment = PaymentModel::first();
    expect($payment->status)->toBe(PaymentStatus::Processing);
    expect($payment->gateway_reference)->toBe('ref_ok');
});

test('update status skips if persistence disabled', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnFalse();
    $logger = Mockery::mock(PaymentLoggerContract::class);

    $step = new UpdatePaymentStatus($config, $logger);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBe('next');
});

test('check idempotency continues if existing payment not found or is pending', function () {
    $config = Mockery::mock(PaymentConfig::class);
    $config->shouldReceive('shouldPersistPayments')->andReturnTrue();

    $step = new CheckIdempotency($config);
    $context = createTestContext();

    $result = $step->handle($context, fn ($ctx) => 'next');
    expect($result)->toBe('next');
});
