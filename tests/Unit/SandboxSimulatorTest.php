<?php

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Services\SandboxSimulator;

test('SandboxSimulator creates successful payment', function () {
    $simulator = new SandboxSimulator;
    $payload = CanonicalPayload::fromArray([
        'order' => ['id' => 'ORD-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    $result = $simulator->simulateCreate($payload);

    expect($result)->toBeInstanceOf(GatewayResult::class);
    expect($result->isSuccessful())->toBeTrue();
    expect($result->gatewayReference)->toStartWith('sim_');
    expect($result->gatewayResponse['simulator'])->toBeTrue();
});

test('SandboxSimulator creates failed payment when order contains FAIL', function () {
    $simulator = new SandboxSimulator;
    $payload = CanonicalPayload::fromArray([
        'order' => ['id' => 'ORD-FAIL-001'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    $result = $simulator->simulateCreate($payload);

    expect($result->status)->toBe(PaymentStatus::Failed);
    expect($result->isSuccessful())->toBeFalse();
});

test('SandboxSimulator simulates refund', function () {
    $simulator = new SandboxSimulator;
    $result = $simulator->simulateRefund('PAY-001', 50.00);

    expect($result->status)->toBe(PaymentStatus::Refunded);
    expect($result->gatewayReference)->toStartWith('sim_ref_');
    expect($result->gatewayResponse['amount'])->toBe(50.00);
});

test('SandboxSimulator generates webhook payload', function () {
    $simulator = new SandboxSimulator;
    $webhook = $simulator->simulateWebhook('payment.completed', 'GW-REF-001');

    expect($webhook['id'])->toStartWith('evt_sim_');
    expect($webhook['type'])->toBe('payment.completed');
    expect($webhook['gateway_reference'])->toBe('GW-REF-001');
    expect($webhook['data']['simulated'])->toBeTrue();
});
