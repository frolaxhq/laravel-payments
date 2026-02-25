<?php

namespace Frolax\Payment;

use Frolax\Payment\Concerns\HasGatewayContext;
use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\RefundPayload;
use Frolax\Payment\Enums\RefundStatus;
use Frolax\Payment\Events\PaymentRefunded;
use Frolax\Payment\Events\PaymentRefundRequested;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Illuminate\Support\Str;

class RefundManager
{
    use HasGatewayContext;

    public function __construct(
        protected GatewayRegistry $registry,
        protected CredentialsRepositoryContract $credentialsRepo,
        protected PaymentLoggerContract $logger,
        protected PaymentConfig $config,
    ) {}

    protected function registry(): GatewayRegistry
    {
        return $this->registry;
    }

    protected function credentialsRepo(): CredentialsRepositoryContract
    {
        return $this->credentialsRepo;
    }

    protected function config(): PaymentConfig
    {
        return $this->config;
    }

    // -------------------------------------------------------
    // Refund
    // -------------------------------------------------------

    /**
     * Refund a payment (if supported by the driver).
     */
    public function refund(array $data): GatewayResult
    {
        $payload = RefundPayload::fromArray($data);
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
}
