<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;

class SandboxSimulator
{
    /**
     * Simulate a payment creation.
     */
    public function simulateCreate(CanonicalPayload $payload): GatewayResult
    {
        $shouldSucceed = !str_contains($payload->order->id, 'FAIL');

        return new GatewayResult(
            status: $shouldSucceed ? PaymentStatus::Completed : PaymentStatus::Failed,
            gatewayReference: 'sim_' . uniqid(),
            redirectUrl: $payload->urls?->return,
            gatewayResponse: [
                'simulator' => true,
                'order_id' => $payload->order->id,
                'amount' => $payload->money->amount,
                'currency' => $payload->money->currency,
                'timestamp' => now()->toISOString(),
            ],
            metadata: ['simulated' => true],
        );
    }

    /**
     * Simulate a refund.
     */
    public function simulateRefund(string $paymentId, float $amount): GatewayResult
    {
        return new GatewayResult(
            status: PaymentStatus::Refunded,
            gatewayReference: 'sim_ref_' . uniqid(),
            redirectUrl: null,
            gatewayResponse: [
                'simulator' => true,
                'refund_for' => $paymentId,
                'amount' => $amount,
            ],
            metadata: ['simulated' => true],
        );
    }

    /**
     * Simulate a webhook payload.
     */
    public function simulateWebhook(string $eventType, string $gatewayReference): array
    {
        return [
            'id' => 'evt_sim_' . uniqid(),
            'type' => $eventType,
            'gateway_reference' => $gatewayReference,
            'created_at' => now()->toISOString(),
            'data' => [
                'status' => 'completed',
                'simulated' => true,
            ],
        ];
    }
}
