<?php

namespace Frolax\Payment;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Contracts\SupportsStatusQuery;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CanonicalRefundPayload;
use Frolax\Payment\DTOs\CanonicalStatusPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\AttemptStatus;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Enums\RefundStatus;
use Frolax\Payment\Events\PaymentCancelled;
use Frolax\Payment\Events\PaymentCreated;
use Frolax\Payment\Events\PaymentFailed;
use Frolax\Payment\Events\PaymentRefundRequested;
use Frolax\Payment\Events\PaymentRefunded;
use Frolax\Payment\Events\PaymentVerified;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Payment
{
    protected ?string $gatewayName = null;
    protected ?string $profile = null;
    protected array $context = [];
    protected ?CredentialsDTO $oneOffCredentials = null;

    public function __construct(
        protected GatewayRegistry $registry,
        protected CredentialsRepositoryContract $credentialsRepo,
        protected PaymentLoggerContract $logger,
    ) {}

    /**
     * Select a gateway by name.
     */
    public function gateway(?string $name = null): static
    {
        $clone = clone $this;
        $clone->gatewayName = $name ?? config('payments.default');

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
     * Create a payment.
     */
    public function create(array $data): GatewayResult
    {
        $payload = CanonicalPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);
        $credentials = $this->resolveCredentials($gateway);

        $this->logger->info('payment.create', "Creating payment for order [{$payload->order->id}] via [{$gateway}]", [
            'gateway' => ['name' => $gateway],
            'payment' => ['order' => ['id' => $payload->order->id], 'money' => $payload->money->toArray()],
            'idempotency_key' => $payload->idempotencyKey,
        ]);

        // Check idempotency: if a payment with this key already exists, return it
        if (config('payments.persistence.enabled') && config('payments.persistence.payments')) {
            $existing = Models\PaymentModel::where('idempotency_key', $payload->idempotencyKey)->first();
            if ($existing && $existing->status !== PaymentStatus::Pending->value) {
                $this->logger->info('payment.create.idempotent', "Idempotent hit for key [{$payload->idempotencyKey}]", [
                    'payment' => ['id' => $existing->id],
                ]);

                return new GatewayResult(
                    status: PaymentStatus::from($existing->status),
                    gatewayReference: $existing->gateway_reference,
                );
            }
        }

        // Persist payment record
        $paymentId = (string) Str::ulid();

        if (config('payments.persistence.enabled') && config('payments.persistence.payments')) {
            Models\PaymentModel::create([
                'id' => $paymentId,
                'order_id' => $payload->order->id,
                'gateway_name' => $gateway,
                'profile' => $this->resolveProfile(),
                'tenant_id' => $this->context['tenant_id'] ?? null,
                'status' => PaymentStatus::Pending->value,
                'amount' => $payload->money->amount,
                'currency' => $payload->money->currency,
                'idempotency_key' => $payload->idempotencyKey,
                'customer_email' => $payload->customer?->email,
                'customer_phone' => $payload->customer?->phone,
                'canonical_payload' => $payload->toArray(),
                'metadata' => $payload->metadata,
            ]);
        }

        // Record attempt
        $attemptNo = 1;
        $startTime = microtime(true);

        try {
            $result = $driver->create($payload, $credentials);
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            // Persist attempt
            if (config('payments.persistence.enabled') && config('payments.persistence.attempts')) {
                Models\PaymentAttempt::create([
                    'id' => (string) Str::ulid(),
                    'payment_id' => $paymentId,
                    'attempt_no' => $attemptNo,
                    'status' => $result->isSuccessful() ? AttemptStatus::Succeeded->value : AttemptStatus::Sent->value,
                    'gateway_reference' => $result->gatewayReference,
                    'request_payload' => $payload->toArray(),
                    'response_payload' => $result->gatewayResponse,
                    'duration_ms' => $elapsed,
                ]);
            }

            // Update payment record
            if (config('payments.persistence.enabled') && config('payments.persistence.payments')) {
                Models\PaymentModel::where('id', $paymentId)->update([
                    'status' => $result->status->value,
                    'gateway_reference' => $result->gatewayReference,
                ]);
            }

            $this->logger->info('payment.created', "Payment [{$paymentId}] created successfully via [{$gateway}]", [
                'payment' => ['id' => $paymentId],
                'gateway' => ['name' => $gateway, 'reference' => $result->gatewayReference],
                'result' => ['status' => $result->status->value],
                'timing' => ['duration_ms' => $elapsed],
            ]);

            event(new PaymentCreated(
                paymentId: $paymentId,
                gateway: $gateway,
                orderId: $payload->order->id,
                amount: $payload->money->amount,
                currency: $payload->money->currency,
                redirectUrl: $result->redirectUrl,
                metadata: $payload->metadata,
            ));

            return $result;

        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            if (config('payments.persistence.enabled') && config('payments.persistence.attempts')) {
                Models\PaymentAttempt::create([
                    'id' => (string) Str::ulid(),
                    'payment_id' => $paymentId,
                    'attempt_no' => $attemptNo,
                    'status' => AttemptStatus::Error->value,
                    'errors' => ['message' => $e->getMessage(), 'code' => $e->getCode()],
                    'duration_ms' => $elapsed,
                ]);
            }

            if (config('payments.persistence.enabled') && config('payments.persistence.payments')) {
                Models\PaymentModel::where('id', $paymentId)->update([
                    'status' => PaymentStatus::Failed->value,
                ]);
            }

            $this->logger->error('payment.create.failed', "Payment creation failed: {$e->getMessage()}", [
                'payment' => ['id' => $paymentId],
                'gateway' => ['name' => $gateway],
                'error' => ['message' => $e->getMessage()],
            ]);

            event(new PaymentFailed(
                paymentId: $paymentId,
                gateway: $gateway,
                errorMessage: $e->getMessage(),
            ));

            throw $e;
        }
    }

    /**
     * Verify a payment from a gateway callback/return request.
     */
    public function verifyFromRequest(Request $request): GatewayResult
    {
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);
        $credentials = $this->resolveCredentials($gateway);

        $this->logger->info('payment.verify', "Verifying payment via [{$gateway}]", [
            'gateway' => ['name' => $gateway],
        ]);

        $result = $driver->verify($request, $credentials);

        if ($result->isSuccessful()) {
            $this->logger->info('payment.verified', "Payment verified successfully via [{$gateway}]", [
                'gateway' => ['name' => $gateway, 'reference' => $result->gatewayReference],
                'verification' => ['paid' => true, 'status' => $result->status->value],
            ]);

            // Update payment record if persisted
            if (config('payments.persistence.enabled') && config('payments.persistence.payments') && $result->gatewayReference) {
                Models\PaymentModel::where('gateway_reference', $result->gatewayReference)
                    ->where('gateway_name', $gateway)
                    ->update(['status' => $result->status->value]);
            }

            event(new PaymentVerified(
                paymentId: $result->gatewayReference ?? '',
                gateway: $gateway,
                status: $result->status->value,
                gatewayReference: $result->gatewayReference,
            ));
        } else {
            $this->logger->warning('payment.verify.failed', "Payment verification indicates non-success via [{$gateway}]", [
                'gateway' => ['name' => $gateway],
                'verification' => ['paid' => false, 'status' => $result->status->value],
                'error' => ['message' => $result->errorMessage],
            ]);
        }

        return $result;
    }

    /**
     * Refund a payment (if supported by the driver).
     */
    public function refund(array $data): GatewayResult
    {
        $payload = CanonicalRefundPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (!$driver instanceof SupportsRefund) {
            throw new UnsupportedCapabilityException($gateway, 'refund');
        }

        $credentials = $this->resolveCredentials($gateway);

        $refundId = (string) Str::ulid();

        if (config('payments.persistence.enabled') && config('payments.persistence.refunds')) {
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

            if (config('payments.persistence.enabled') && config('payments.persistence.refunds')) {
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
            if (config('payments.persistence.enabled') && config('payments.persistence.refunds')) {
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

    /**
     * Query payment status (if supported by the driver).
     */
    public function status(array $data): GatewayResult
    {
        $payload = CanonicalStatusPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (!$driver instanceof SupportsStatusQuery) {
            throw new UnsupportedCapabilityException($gateway, 'status');
        }

        $credentials = $this->resolveCredentials($gateway);

        return $driver->status($payload, $credentials);
    }

    // -------------------------------------------------------
    // Internal resolution
    // -------------------------------------------------------

    protected function resolveGatewayName(): string
    {
        return $this->gatewayName ?? config('payments.default', 'dummy');
    }

    protected function resolveProfile(): string
    {
        return $this->profile ?? config('payments.profile', 'test');
    }

    protected function resolveDriver(string $gateway): GatewayDriverContract
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
