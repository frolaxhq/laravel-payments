<?php

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Exceptions\GatewayNotFoundException;
use Frolax\Payment\GatewayRegistry;
use Illuminate\Http\Request;

// -------------------------------------------------------
// GatewayRegistry
// -------------------------------------------------------

test('registry registers and resolves a gateway', function () {
    $registry = new GatewayRegistry;

    $driver = new class implements GatewayDriverContract
    {
        public function name(): string
        {
            return 'dummy';
        }

        public function create(CanonicalPayload $p, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed);
        }

        public function verify(Request $r, CredentialsDTO $c): GatewayResult
        {
            return new GatewayResult(status: PaymentStatus::Completed);
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

    $registry->register('dummy', fn () => $driver, 'Dummy', ['redirect']);

    expect($registry->has('dummy'))->toBeTrue();
    expect($registry->resolve('dummy'))->toBe($driver);
    expect($registry->keys())->toContain('dummy');
});

test('registry throws for unknown gateway', function () {
    $registry = new GatewayRegistry;
    $registry->resolve('nonexistent');
})->throws(GatewayNotFoundException::class);

test('registry lists all gateways', function () {
    $registry = new GatewayRegistry;
    $registry->register('gw1', fn () => null, 'Gateway 1');
    $registry->register('gw2', fn () => null, 'Gateway 2');

    expect($registry->all())->toHaveCount(2);
    expect($registry->keys())->toEqual(['gw1', 'gw2']);
});
