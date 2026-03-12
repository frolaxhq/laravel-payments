<?php

namespace Frolax\Payment\Pipeline;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;

/**
 * Immutable context object passed through the payment pipeline.
 *
 * All properties are readonly. State transitions use named constructors
 * that return new instances, leaving the original unchanged.
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
        public readonly ?string $paymentId = null,
        public readonly ?int $attemptNo = null,
        public readonly ?GatewayResult $result = null,
        public readonly ?float $startTime = null,
        public readonly ?float $elapsedMs = null,
        public readonly ?\Throwable $exception = null,
    ) {}

    public function withPaymentId(string $paymentId): self
    {
        return new self(
            gateway: $this->gateway,
            profile: $this->profile,
            driver: $this->driver,
            payload: $this->payload,
            credentials: $this->credentials,
            tenantId: $this->tenantId,
            paymentId: $paymentId,
            attemptNo: $this->attemptNo,
            result: $this->result,
            startTime: $this->startTime,
            elapsedMs: $this->elapsedMs,
            exception: $this->exception,
        );
    }

    public function withAttemptNo(int $attemptNo): self
    {
        return new self(
            gateway: $this->gateway,
            profile: $this->profile,
            driver: $this->driver,
            payload: $this->payload,
            credentials: $this->credentials,
            tenantId: $this->tenantId,
            paymentId: $this->paymentId,
            attemptNo: $attemptNo,
            result: $this->result,
            startTime: $this->startTime,
            elapsedMs: $this->elapsedMs,
            exception: $this->exception,
        );
    }

    public function withResult(GatewayResult $result): self
    {
        return new self(
            gateway: $this->gateway,
            profile: $this->profile,
            driver: $this->driver,
            payload: $this->payload,
            credentials: $this->credentials,
            tenantId: $this->tenantId,
            paymentId: $this->paymentId,
            attemptNo: $this->attemptNo,
            result: $result,
            startTime: $this->startTime,
            elapsedMs: $this->elapsedMs,
            exception: $this->exception,
        );
    }

    public function withTiming(float $startTime, float $elapsedMs): self
    {
        return new self(
            gateway: $this->gateway,
            profile: $this->profile,
            driver: $this->driver,
            payload: $this->payload,
            credentials: $this->credentials,
            tenantId: $this->tenantId,
            paymentId: $this->paymentId,
            attemptNo: $this->attemptNo,
            result: $this->result,
            startTime: $startTime,
            elapsedMs: $elapsedMs,
            exception: $this->exception,
        );
    }

    public function withException(\Throwable $exception): self
    {
        return new self(
            gateway: $this->gateway,
            profile: $this->profile,
            driver: $this->driver,
            payload: $this->payload,
            credentials: $this->credentials,
            tenantId: $this->tenantId,
            paymentId: $this->paymentId,
            attemptNo: $this->attemptNo,
            result: $this->result,
            startTime: $this->startTime,
            elapsedMs: $this->elapsedMs,
            exception: $exception,
        );
    }
}
