<?php

namespace Frolax\Payment;

use Frolax\Payment\Concerns\HasGatewayContext;
use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsStatusQuery;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CanonicalStatusPayload;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Frolax\Payment\Pipeline\PaymentContext;
use Frolax\Payment\Pipeline\Steps\CheckIdempotency;
use Frolax\Payment\Pipeline\Steps\DispatchPaymentEvent;
use Frolax\Payment\Pipeline\Steps\ExecuteGatewayCall;
use Frolax\Payment\Pipeline\Steps\PersistAttempt;
use Frolax\Payment\Pipeline\Steps\PersistPaymentRecord;
use Frolax\Payment\Pipeline\Steps\UpdatePaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class Payment
{
    use HasGatewayContext;

    public function __construct(
        protected GatewayRegistry $registry,
        protected CredentialsRepositoryContract $credentialsRepo,
        protected PaymentLoggerContract $logger,
        protected PaymentConfig $config,
    ) {}

    // -------------------------------------------------------
    // Dependency accessors (required by HasGatewayContext)
    // -------------------------------------------------------

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
    // One-off Payments
    // -------------------------------------------------------

    /**
     * Create a one-off payment via a composable pipeline.
     */
    public function charge(array $data): GatewayResult
    {
        $payload = CanonicalPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);
        $credentials = $this->resolveCredentials($gateway);

        $context = new PaymentContext(
            gateway: $gateway,
            profile: $this->resolveProfile(),
            driver: $driver,
            payload: $payload,
            credentials: $credentials,
            tenantId: $this->context['tenant_id'] ?? null,
        );

        /** @var PaymentContext $result */
        $result = app(Pipeline::class)
            ->send($context)
            ->through([
                CheckIdempotency::class,
                PersistPaymentRecord::class,
                ExecuteGatewayCall::class,
                PersistAttempt::class,
                UpdatePaymentStatus::class,
                DispatchPaymentEvent::class,
            ])
            ->thenReturn();

        return $result->result;
    }

    /**
     * Alias for charge() — backward compatibility.
     */
    public function create(array $data): GatewayResult
    {
        return $this->charge($data);
    }

    // -------------------------------------------------------
    // Verification & Status
    // -------------------------------------------------------

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
            if ($this->config->shouldPersistPayments() && $result->gatewayReference) {
                Models\PaymentModel::where('gateway_reference', $result->gatewayReference)
                    ->where('gateway_name', $gateway)
                    ->update(['status' => $result->status->value]);
            }

            event(new Events\PaymentVerified(
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
     * Query payment status (if supported by the driver).
     */
    public function status(array $data): GatewayResult
    {
        $payload = CanonicalStatusPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsStatusQuery) {
            throw new UnsupportedCapabilityException($gateway, 'status');
        }

        $credentials = $this->resolveCredentials($gateway);

        return $driver->status($payload, $credentials);
    }

    // -------------------------------------------------------
    // Subscription delegation
    // -------------------------------------------------------

    /**
     * Create a subscription via the gateway.
     */
    public function subscribe(array $data): GatewayResult
    {
        return $this->forwardToSubscriptionManager()->create($data);
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): GatewayResult
    {
        return $this->forwardToSubscriptionManager()->cancel($subscriptionId, $immediately);
    }

    /**
     * Pause a subscription.
     */
    public function pauseSubscription(string $subscriptionId): GatewayResult
    {
        return $this->forwardToSubscriptionManager()->pause($subscriptionId);
    }

    /**
     * Resume a paused subscription.
     */
    public function resumeSubscription(string $subscriptionId): GatewayResult
    {
        return $this->forwardToSubscriptionManager()->resume($subscriptionId);
    }

    /**
     * Update a subscription (plan change, quantity, etc).
     */
    public function updateSubscription(string $subscriptionId, array $changes): GatewayResult
    {
        return $this->forwardToSubscriptionManager()->update($subscriptionId, $changes);
    }

    // -------------------------------------------------------
    // Refund delegation
    // -------------------------------------------------------

    /**
     * Refund a payment.
     */
    public function refund(array $data): GatewayResult
    {
        return $this->forwardToRefundManager()->refund($data);
    }

    // -------------------------------------------------------
    // Static discovery helpers
    // -------------------------------------------------------

    /**
     * Get all registered gateways.
     */
    public static function gateways(): array
    {
        return app(GatewayRegistry::class)->all();
    }

    /**
     * Get all gateways that support a specific capability.
     *
     * @param  class-string  $capability
     */
    public static function gatewaysThatSupport(string $capability): array
    {
        return app(GatewayRegistry::class)->supporting($capability);
    }

    // -------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------

    protected function subscriptionManager(): SubscriptionManager
    {
        return app(SubscriptionManager::class);
    }

    protected function forwardToSubscriptionManager(): SubscriptionManager
    {
        $manager = $this->subscriptionManager()->gateway($this->resolveGatewayName())
            ->withProfile($this->resolveProfile())
            ->usingContext($this->context);

        if ($this->oneOffCredentials) {
            $manager = $manager->usingCredentials($this->oneOffCredentials->credentials);
        }

        return $manager;
    }

    protected function forwardToRefundManager(): RefundManager
    {
        $manager = app(RefundManager::class)->gateway($this->resolveGatewayName())
            ->withProfile($this->resolveProfile())
            ->usingContext($this->context);

        if ($this->oneOffCredentials) {
            $manager = $manager->usingCredentials($this->oneOffCredentials->credentials);
        }

        return $manager;
    }
}
