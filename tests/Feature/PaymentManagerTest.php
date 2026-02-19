<?php

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Exceptions\GatewayNotFoundException;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Payment;
use Illuminate\Http\Request;

function createDummyDriver(): GatewayDriverContract
{
    return new class implements GatewayDriverContract
    {
        public function name(): string
        {
            return 'dummy';
        }

        public function create(CanonicalPayload $p, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(
                status: PaymentStatus::Pending,
                gatewayReference: 'GW-REF-'.$p->idempotencyKey,
                redirectUrl: 'https://gateway.test/pay',
            );
        }

        public function verify(Request $r, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed, gatewayReference: 'GW-REF-001');
        }

        public function setCredentials(CredentialsDTO $c): static
        {
            return $this;
        }

        public function capabilities(): array
        {
            return ['redirect'];
        }
    };
}

test('Payment manager creates a payment via a gateway', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('dummy', fn () => createDummyDriver(), 'Dummy', ['redirect']);

    config()->set('payments.gateways.dummy.test', ['key' => 'test_key']);

    $payment = app(Payment::class);
    $result = $payment->gateway('dummy')->create([
        'idempotency_key' => 'test-create-001',
        'order' => ['id' => 'ORD-001', 'description' => 'Test'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    expect($result)->toBeInstanceOf(GatewayResult::class);
    expect($result->requiresRedirect())->toBeTrue();
    expect($result->redirectUrl)->toBe('https://gateway.test/pay');
});

test('Payment manager throws for unknown gateway', function () {
    $payment = app(Payment::class);
    $payment->gateway('nonexistent')->create([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);
})->throws(GatewayNotFoundException::class);

test('Payment manager throws for missing credentials', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('no_creds', fn () => createDummyDriver(), 'No Creds');

    $payment = app(Payment::class);
    $payment->gateway('no_creds')->create([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);
})->throws(MissingCredentialsException::class);

test('Payment manager throws for unsupported refund', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('no_refund', fn () => createDummyDriver(), 'No Refund');

    config()->set('payments.gateways.no_refund.test', ['key' => 'k']);

    $payment = app(Payment::class);
    $payment->gateway('no_refund')->refund([
        'payment_id' => 'PAY-001',
        'money' => ['amount' => 50, 'currency' => 'USD'],
    ]);
})->throws(UnsupportedCapabilityException::class);

test('Payment manager supports fluent context and profile', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('fluent_gw', fn () => createDummyDriver(), 'Fluent');

    config()->set('payments.gateways.fluent_gw.live', ['key' => 'live_key']);

    $payment = app(Payment::class);
    $result = $payment
        ->gateway('fluent_gw')
        ->withProfile('live')
        ->usingContext(['tenant_id' => 'tenant-001'])
        ->create([
            'idempotency_key' => 'fluent-test-001',
            'order' => ['id' => 'ORD-F01'],
            'money' => ['amount' => 200, 'currency' => 'BDT'],
        ]);

    expect($result)->toBeInstanceOf(GatewayResult::class);
});

test('Payment manager supports one-off credentials', function () {
    $registry = app(GatewayRegistry::class);
    $registry->register('oneoff_gw', fn () => createDummyDriver(), 'OneOff');

    $payment = app(Payment::class);
    $result = $payment
        ->gateway('oneoff_gw')
        ->usingCredentials(['key' => 'override_key', 'secret' => 'override_secret'])
        ->create([
            'idempotency_key' => 'oneoff-test-001',
            'order' => ['id' => 'ORD-O01'],
            'money' => ['amount' => 300, 'currency' => 'USD'],
        ]);

    expect($result)->toBeInstanceOf(GatewayResult::class);
});
