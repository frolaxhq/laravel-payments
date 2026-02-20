<?php

namespace Frolax\Payment;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\DTOs\CanonicalRefundPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\RefundStatus;
use Frolax\Payment\Events\PaymentRefunded;
use Frolax\Payment\Events\PaymentRefundRequested;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Illuminate\Support\Str;

class RefundManager
{
    protected ?string $gatewayName = null;

    protected ?string $profile = null;

    protected array $context = [];

    protected ?CredentialsDTO $oneOffCredentials = null;

    public function __construct(
        protected GatewayRegistry $registry,
        protected CredentialsRepositoryContract $credentialsRepo,
        protected PaymentLoggerContract $logger,
        protected PaymentConfig $config,
    ) {}

    /**
     * Select a gateway by name.
     */
    public function gateway(?string $name = null): static
    {
        $clone = clone $this;
        $clone->gatewayName = $name ?? $this->config->defaultGateway;

        return $clone;
    }

    /**
     * Set runtime context (e.g. tenant_id).
     */
    public function usingContext(array $context): static
    {
        $clone = clone $this;
        $clone->context = array_merge($clone->context, $context);

        return $clone;
    }

    /**
     * Select a credential profile (test/live).
     */
    public function withProfile(string $profile): static
    {
        $clone = clone $this;
        $clone->profile = $profile;

        return $clone;
    }

    /**
     * Use one-off credentials (not resolved from repo).
     */
    public function usingCredentials(array $credentials): static
    {
        $clone = clone $this;
        $clone->oneOffCredentials = new CredentialsDTO(
            gateway: $clone->resolveGatewayName(),
            profile: $clone->resolveProfile(),
            credentials: $credentials,
            tenantId: $clone->context['tenant_id'] ?? null,
        );

        return $clone;
    }

    /**
     * Refund a payment (if supported by the driver).
     */
    public function refund(array $data): GatewayResult
    {
        $payload = CanonicalRefundPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRefund) {
            throw new UnsupportedCapabilityException($gateway, 'refund');
        }

        $credentials = $this->resolveCredentials($gateway);

        $refundId = (string) Str::ulid();

        if ($this->config->shouldPersistRefunds()) {
            Models\PaymentRefund::create([
                'id' => $refundId,
                'payment_id' => $payload->paymentId,
                'amount' => $payload->money->amount,
                'currency' => $payload->money->currency,
                'status' => RefundStatus::Pending->value,
                'reason' => $payload->reason,
                'request_payload' => $payload->toArray(),
                'metadata' => $payload->metadata,
            ]);
        }

        $this->logger->info('payment.refund', "Refund requested for payment [{$payload->paymentId}] via [{$gateway}]", [
            'payment' => ['id' => $payload->paymentId],
            'refund' => ['id' => $refundId, 'amount' => $payload->money->amount],
            'gateway' => ['name' => $gateway],
        ]);

        event(new PaymentRefundRequested(
            paymentId: $payload->paymentId,
            refundId: $refundId,
            gateway: $gateway,
            amount: $payload->money->amount,
            currency: $payload->money->currency,
            reason: $payload->reason,
        ));

        try {
            $result = $driver->refund($payload, $credentials);

            if ($this->config->shouldPersistRefunds()) {
                Models\PaymentRefund::where('id', $refundId)->update([
                    'status' => $result->isSuccessful() ? RefundStatus::Completed->value : RefundStatus::Failed->value,
                    'refund_reference' => $result->gatewayReference,
                    'response_payload' => $result->gatewayResponse,
                ]);
            }

            event(new PaymentRefunded(
                paymentId: $payload->paymentId,
                refundId: $refundId,
                gateway: $gateway,
                amount: $payload->money->amount,
                currency: $payload->money->currency,
                status: $result->status->value,
                refundReference: $result->gatewayReference,
            ));

            return $result;

        } catch (\Throwable $e) {
            if ($this->config->shouldPersistRefunds()) {
                Models\PaymentRefund::where('id', $refundId)->update([
                    'status' => RefundStatus::Failed->value,
                ]);
            }

            $this->logger->error('payment.refund.failed', "Refund failed: {$e->getMessage()}", [
                'refund' => ['id' => $refundId],
                'error' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    // -------------------------------------------------------
    // Internal resolution
    // -------------------------------------------------------

    protected function resolveGatewayName(): string
    {
        return $this->gatewayName ?? $this->config->defaultGateway;
    }

    protected function resolveProfile(): string
    {
        return $this->profile ?? $this->config->defaultProfile;
    }

    protected function resolveDriver(string $gateway): Contracts\GatewayDriverContract
    {
        return $this->registry->resolve($gateway);
    }

    protected function resolveCredentials(string $gateway): CredentialsDTO
    {
        if ($this->oneOffCredentials) {
            return $this->oneOffCredentials;
        }

        $profile = $this->resolveProfile();
        $credentials = $this->credentialsRepo->get($gateway, $profile, $this->context);

        if ($credentials === null) {
            throw new MissingCredentialsException($gateway, $profile, $this->context['tenant_id'] ?? null);
        }

        return $credentials;
    }
}
