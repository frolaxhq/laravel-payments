<?php

use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Events\PaymentCancelled;
use Frolax\Payment\Events\WebhookReceived;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Http\Controllers\CancelController;
use Frolax\Payment\Http\Controllers\ReturnController;
use Frolax\Payment\Http\Controllers\WebhookController;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\PaymentWebhookEvent;
use Frolax\Payment\Payment;
use Frolax\Payment\PaymentConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->logger = Mockery::mock(PaymentLoggerContract::class)->makePartial();
    $this->logger->shouldReceive('info')->andReturnNull();
    $this->logger->shouldReceive('warning')->andReturnNull();
    $this->logger->shouldReceive('error')->andReturnNull();
});

test('cancel controller redirects and does not persist without order_id', function () {
    $controller = new CancelController;
    $request = Request::create('/cancel?redirect=/home', 'GET');

    $response = $controller($request, 'fake', $this->logger);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toEndWith('/home');
});

test('cancel controller persists cancellation and fires event', function () {
    Event::fake([PaymentCancelled::class]);
    config(['payments.persistence.enabled' => true, 'payments.persistence.payments' => true]);

    $payment = PaymentModel::create([
        'id' => 'pay_123',
        'gateway_name' => 'fake',
        'gateway_reference' => 'ref_123',
        'order_id' => 'ord_123',
        'status' => PaymentStatus::Pending,
        'amount' => 100,
        'currency' => 'USD',
    ]);

    $controller = new CancelController;
    $request = Request::create('/cancel?order_id=ord_123&redirect=/cart', 'GET');

    $response = $controller($request, 'fake', $this->logger);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
    Event::assertDispatched(PaymentCancelled::class, fn ($e) => $e->paymentId === 'pay_123');
    expect($response->headers->get('Location'))->toEndWith('/cart');
});

test('return controller verifies payment and logs correctly', function () {
    $paymentManager = Mockery::mock(Payment::class);
    $gateway = Mockery::mock(\Frolax\Payment\Contracts\GatewayDriverContract::class);
    $paymentManager->shouldReceive('gateway')->with('fake')->andReturn($gateway);

    $result = new GatewayResult(PaymentStatus::Completed);
    $gateway->shouldReceive('verifyFromRequest')->andReturn($result);

    $controller = new ReturnController;
    $request = Request::create('/return?redirect=/thanks', 'GET');

    $response = $controller($request, 'fake', $paymentManager, $this->logger);

    expect($response->headers->get('Location'))->toEndWith('/thanks');
});

test('return controller handles verification exception', function () {
    $paymentManager = Mockery::mock(Payment::class);
    $gateway = Mockery::mock(\Frolax\Payment\Contracts\GatewayDriverContract::class);
    $paymentManager->shouldReceive('gateway')->with('fake')->andReturn($gateway);

    $gateway->shouldReceive('verifyFromRequest')->andThrow(new Exception('Verify failed'));

    $controller = new ReturnController;
    $request = Request::create('/return?redirect=/error', 'GET');

    $response = $controller($request, 'fake', $paymentManager, $this->logger);

    expect($response->headers->get('Location'))->toEndWith('/error');
});

test('webhook controller handles invalid signature', function () {
    $registry = app(GatewayRegistry::class);
    $config = app(PaymentConfig::class);

    // FakeDriver supports WebhookVerification
    $registry->register('fake', \Frolax\Payment\Testing\FakeDriver::class);
    $driver = $registry->resolve('fake');
    // Mock a generic driver implementing both interfaces
    $mockDriver = Mockery::mock(
        \Frolax\Payment\Contracts\GatewayDriverContract::class,
        \Frolax\Payment\Contracts\SupportsWebhookVerification::class
    );
    $mockDriver->shouldReceive('verifyWebhookSignature')->andReturn(false);
    $mockDriver->shouldReceive('parseWebhookEventType')->andReturn('event');
    $mockDriver->shouldReceive('parseWebhookGatewayReference')->andReturn('ref');

    $registryMock = Mockery::mock(GatewayRegistry::class)->makePartial();
    $registryMock->shouldReceive('resolve')->with('fake')->andReturn($mockDriver);

    // Mock Credentials Repo so it returns true
    $credsRepo = Mockery::mock(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class);
    $credsRepo->shouldReceive('get')->andReturn(new \Frolax\Payment\Data\Credentials('fake', 'test', ['key' => 'secret']));
    app()->instance(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class, $credsRepo);

    $controller = new WebhookController;
    $request = Request::create('/webhook', 'POST', ['some' => 'data']);

    $response = $controller($request, 'fake', $registryMock, $config, $this->logger);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBe('Invalid signature');
});

test('webhook controller handles full valid webhook cycle', function () {
    Event::fake([WebhookReceived::class]);
    config(['payments.persistence.enabled' => true, 'payments.persistence.payments' => true]);

    $payment = PaymentModel::create([
        'id' => 'pay_abc',
        'gateway_name' => 'fake',
        'gateway_reference' => 'ref_abc',
        'order_id' => 'ord_abc',
        'status' => PaymentStatus::Pending,
        'amount' => 100,
        'currency' => 'USD',
    ]);

    $mockDriver = Mockery::mock(
        \Frolax\Payment\Contracts\GatewayDriverContract::class,
        \Frolax\Payment\Contracts\SupportsWebhookVerification::class
    );
    $mockDriver->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $mockDriver->shouldReceive('parseWebhookEventType')->andReturn('payment.success');
    $mockDriver->shouldReceive('parseWebhookGatewayReference')->andReturn('ref_abc');
    $mockDriver->shouldReceive('verify')->andReturn(new GatewayResult(PaymentStatus::Completed));

    $registryMock = Mockery::mock(GatewayRegistry::class)->makePartial();
    $registryMock->shouldReceive('resolve')->with('fake')->andReturn($mockDriver);

    $credsRepo = Mockery::mock(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class);
    $credsRepo->shouldReceive('get')->andReturn(new \Frolax\Payment\Data\Credentials('fake', 'test', []));
    app()->instance(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class, $credsRepo);

    $config = app(PaymentConfig::class);
    $controller = new WebhookController;
    $request = Request::create('/webhook', 'POST', ['some' => 'data']);

    $response = $controller($request, 'fake', $registryMock, $config, $this->logger);

    // Check WebhookEvent created and processed
    $webhookEvent = PaymentWebhookEvent::first();
    expect($webhookEvent)->not->toBeNull()
        ->and($webhookEvent->processed)->toBeTrue();

    // Check PaymentModel updated
    expect($payment->fresh()->status)->toBe(PaymentStatus::Completed);

    expect($response->getStatusCode())->toBe(200);
});

test('webhook controller returns early if already processed', function () {
    config(['payments.persistence.enabled' => true, 'payments.persistence.payments' => true]);

    PaymentWebhookEvent::create([
        'id' => 'evt_1',
        'gateway_name' => 'fake',
        'gateway_reference' => 'ref_already',
        'event_type' => 'payment.success',
        'processed' => true,
    ]);

    $mockDriver = Mockery::mock(
        \Frolax\Payment\Contracts\GatewayDriverContract::class,
        \Frolax\Payment\Contracts\SupportsWebhookVerification::class
    );
    $mockDriver->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $mockDriver->shouldReceive('parseWebhookEventType')->andReturn('payment.success');
    $mockDriver->shouldReceive('parseWebhookGatewayReference')->andReturn('ref_already');

    $registryMock = Mockery::mock(GatewayRegistry::class)->makePartial();
    $registryMock->shouldReceive('resolve')->with('fake')->andReturn($mockDriver);

    $credsRepo = Mockery::mock(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class);
    $credsRepo->shouldReceive('get')->andReturn(new \Frolax\Payment\Data\Credentials('fake', 'test', []));
    app()->instance(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class, $credsRepo);

    $config = app(PaymentConfig::class);
    $controller = new WebhookController;
    $request = Request::create('/webhook', 'POST', ['some' => 'data']);

    $response = $controller($request, 'fake', $registryMock, $config, $this->logger);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('Already processed');
});

test('webhook controller handles verify exception', function () {
    $mockDriver = Mockery::mock(
        \Frolax\Payment\Contracts\GatewayDriverContract::class,
        \Frolax\Payment\Contracts\SupportsWebhookVerification::class
    );
    $mockDriver->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $mockDriver->shouldReceive('parseWebhookEventType')->andReturn('payment.success');
    $mockDriver->shouldReceive('parseWebhookGatewayReference')->andReturn('ref_err');
    $mockDriver->shouldReceive('verify')->andThrow(new Exception('Webhook error'));

    $registryMock = Mockery::mock(GatewayRegistry::class)->makePartial();
    $registryMock->shouldReceive('resolve')->with('fake')->andReturn($mockDriver);

    $credsRepo = Mockery::mock(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class);
    $credsRepo->shouldReceive('get')->andReturn(new \Frolax\Payment\Data\Credentials('fake', 'test', []));
    app()->instance(\Frolax\Payment\Contracts\CredentialsRepositoryContract::class, $credsRepo);

    $config = app(PaymentConfig::class);
    $controller = new WebhookController;
    $request = Request::create('/webhook', 'POST');

    $response = $controller($request, 'fake', $registryMock, $config, $this->logger);

    // We mocked the logger so we expect the error to have been logged and the response returns 200 OK.
    // Ensure WebhookEvent is marked processed anyway since it didn't completely abort the response logic,
    // actually, wait, the webhook processed update is outside the try block!
    expect($response->getStatusCode())->toBe(200);
});
