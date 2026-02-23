<?php

namespace Frolax\Payment\Pipeline;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

/**
 * Immutable context object passed through the payment pipeline.
 */
final class PaymentContext
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $profile,
        public readonly GatewayDriverContract $driver,
        public readonly CanonicalPayload $payload,
        public readonly CredentialsDTO $credentials,
        public readonly ?string $tenantId = null,
        public ?string $paymentId = null,
        public ?int $attemptNo = null,
        public ?GatewayResult $result = null,
        public ?float $startTime = null,
        public ?float $elapsedMs = null,
        public ?\Throwable $exception = null,
    ) {}
}
