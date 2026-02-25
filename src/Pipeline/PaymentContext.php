<?php

namespace Frolax\Payment\Pipeline;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;

/**
 * Immutable context object passed through the payment pipeline.
 */
final class PaymentContext
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $profile,
        public readonly GatewayDriverContract $driver,
        public readonly Payload $payload,
        public readonly Credentials $credentials,
        public readonly ?string $tenantId = null,
        public ?string $paymentId = null,
        public ?int $attemptNo = null,
        public ?GatewayResult $result = null,
        public ?float $startTime = null,
        public ?float $elapsedMs = null,
        public ?\Throwable $exception = null,
    ) {}
}
