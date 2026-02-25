<?php

use Frolax\Payment\Events\WebhookReceived;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Models\PaymentWebhookEvent;
use Frolax\Payment\Testing\FakeDriver;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->registry = app(GatewayRegistry::class);
    $this->registry->register('fake_gateway', FakeDriver::class);
});

test('replay webhook command fails when event not found', function () {
    $this->artisan('payments:webhooks:replay', ['id' => 999])
        ->expectsOutputToContain('not found.')
        ->assertExitCode(1);
});

test('replay webhook command cancels on no confirmation', function () {
    $event = PaymentWebhookEvent::forceCreate([
        'gateway_name' => 'fake_gateway',
        'event_type' => 'test.event',
        'gateway_reference' => 'ref-1',
        'processed' => true,
        'payload' => ['id' => '123'],
        'headers' => ['x-signature' => ['abc']],
    ]);

    $this->artisan('payments:webhooks:replay', ['id' => $event->id])
        ->expectsConfirmation('Do you want to proceed with the replay?', 'no')
        ->assertExitCode(0);
});

test('replay webhook command fails on missing credentials', function () {
    Event::fake([WebhookReceived::class]);
    // Clear config
    config()->set('payments.gateways.fake_gateway.test', null);

    $eventModel = PaymentWebhookEvent::forceCreate([
        'gateway_name' => 'fake_gateway',
        'event_type' => 'test.event',
        'gateway_reference' => 'ref-1',
        'processed' => true,
        'payload' => ['id' => '123'],
        'headers' => ['x-signature' => ['abc']],
    ]);

    $this->artisan('payments:webhooks:replay', ['id' => $eventModel->id])
        ->expectsConfirmation('Do you want to proceed with the replay?', 'yes')
        ->expectsOutputToContain('No credentials found for this gateway.')
        ->assertExitCode(1);
});

test('replay webhook command succeeds on valid event', function () {
    Event::fake([WebhookReceived::class]);
    config()->set('payments.gateways.fake_gateway.test.key', 'val');

    $eventModel = PaymentWebhookEvent::forceCreate([
        'gateway_name' => 'fake_gateway',
        'event_type' => 'test.event',
        'gateway_reference' => 'ref-1',
        'processed' => true,
        'payload' => ['id' => '123'],
        'headers' => ['x-signature' => ['abc']],
    ]);

    $this->artisan('payments:webhooks:replay', ['id' => $eventModel->id])
        ->expectsConfirmation('Do you want to proceed with the replay?', 'yes')
        ->expectsOutputToContain('replayed successfully.')
        ->assertExitCode(0);

    Event::assertDispatched(WebhookReceived::class, function ($e) {
        return $e->gatewayReference === 'ref-1' && $e->eventType === 'test.event';
    });
});

test('replay webhook command fails on exception', function () {
    // No credentials will cause MissingCredentialsException or missing GatewayRequestFailedException if driver fails
    // Here we will mock driver to throw exception
    $mockDriver = new class implements \Frolax\Payment\Contracts\GatewayDriverContract
    {
        public function name(): string
        {
            return 'fail_gateway';
        }

        public function setCredentials(\Frolax\Payment\Data\Credentials $credentials): static
        {
            return $this;
        }

        public function create(\Frolax\Payment\Data\Payload $payload, \Frolax\Payment\Data\Credentials $credentials): \Frolax\Payment\Data\GatewayResult
        {
            throw new \Exception('Failed intentionally');
        }

        public function verify(\Illuminate\Http\Request $request, \Frolax\Payment\Data\Credentials $credentials): \Frolax\Payment\Data\GatewayResult
        {
            throw new \Exception('Failed verification intentionally');
        }
    };

    $this->registry->register('fail_gateway', get_class($mockDriver));
    // Since driverClass uses `app()->make(...)`, actually it might be easier to just bind it
    app()->instance(get_class($mockDriver), $mockDriver);
    config()->set('payments.gateways.fail_gateway.test.key', 'val');

    $eventModel = PaymentWebhookEvent::forceCreate([
        'gateway_name' => 'fail_gateway',
        'event_type' => 'test.event',
        'gateway_reference' => 'ref-1',
        'processed' => true,
        'payload' => ['id' => '123'],
        'headers' => ['x-signature' => ['abc']],
    ]);

    $this->artisan('payments:webhooks:replay', ['id' => $eventModel->id])
        ->expectsConfirmation('Do you want to proceed with the replay?', 'yes')
        ->expectsOutputToContain('Replay failed:')
        ->assertExitCode(1);
});
