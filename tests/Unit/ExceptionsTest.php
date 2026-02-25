<?php

use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Exceptions\GatewayNotFoundException;
use Frolax\Payment\Exceptions\GatewayRequestFailedException;
use Frolax\Payment\Exceptions\InvalidPayloadException;
use Frolax\Payment\Exceptions\InvalidSignatureException;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Frolax\Payment\Exceptions\VerificationMismatchException;

test('exceptions instantiate correctly with right messages', function () {
    $e1 = new UnsupportedCapabilityException('stripe', 'refund');
    expect($e1->getMessage())->toBe('Gateway [stripe] does not support [refund].');

    $result = new GatewayResult(PaymentStatus::Failed);
    $e2 = new GatewayRequestFailedException('stripe', 'Request failed', ['error' => 'boom']);
    expect($e2->getMessage())->toBe('[stripe] Request failed');
    expect($e2->response())->toBe(['error' => 'boom']);

    $e3 = new InvalidPayloadException('Bad payload', ['field' => 'Required']);
    expect($e3->getMessage())->toBe('Bad payload');
    expect($e3->errors())->toBe(['field' => 'Required']);

    $e4 = new GatewayNotFoundException('Not found');
    expect($e4->getMessage())->toBe('Not found');

    $e5 = new VerificationMismatchException('Mismatch');
    expect($e5->getMessage())->toBe('Mismatch');

    $e6 = new InvalidSignatureException('Bad sig');
    expect($e6->getMessage())->toBe('Bad sig');

    $e7 = new MissingCredentialsException('stripe', 'live');
    expect($e7->getMessage())->toBe('Missing credentials for gateway [stripe] profile [live].');

    $e8 = new MissingCredentialsException('stripe', 'live', 'tenant-1');
    expect($e8->getMessage())->toBe('Missing credentials for gateway [stripe] profile [live] tenant [tenant-1].');
});
