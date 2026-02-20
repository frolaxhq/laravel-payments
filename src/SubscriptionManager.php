<?php

namespace Frolax\Payment;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\DTOs\CanonicalSubscriptionPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\SubscriptionStatus;
use Frolax\Payment\Events\SubscriptionCancelled;
use Frolax\Payment\Events\SubscriptionCreated;
use Frolax\Payment\Events\SubscriptionPaused;
use Frolax\Payment\Events\SubscriptionResumed;
use Frolax\Payment\Exceptions\MissingCredentialsException;
use Frolax\Payment\Exceptions\UnsupportedCapabilityException;
use Frolax\Payment\Models\Subscription;

class SubscriptionManager
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
     * Create a subscription.
     */
    public function create(array $data): GatewayResult
    {
        $payload = CanonicalSubscriptionPayload::fromArray($data);
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRecurring) {
            throw new UnsupportedCapabilityException($gateway, 'recurring');
        }

        $credentials = $this->resolveCredentials($gateway);
        $result = $driver->createSubscription($payload, $credentials);

        // Persist subscription record
        $subscription = Subscription::create([
            'gateway_name' => $gateway,
            'profile' => $this->resolveProfile(),
            'tenant_id' => $this->context['tenant_id'] ?? null,
            'customer_id' => $payload->customer?->email,
            'customer_email' => $payload->customer?->email,
            'plan_id' => $payload->plan->id,
            'plan_name' => $payload->plan->name,
            'status' => $result->isSuccessful() ? SubscriptionStatus::Active : SubscriptionStatus::Incomplete,
            'gateway_subscription_id' => $result->gatewayReference,
            'quantity' => $payload->quantity,
            'amount' => $payload->plan->money->amount,
            'currency' => $payload->plan->money->currency,
            'interval' => $payload->plan->interval,
            'interval_count' => $payload->plan->intervalCount,
            'trial_ends_at' => $payload->trialDays ? now()->addDays($payload->trialDays) : null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => $payload->metadata,
            'gateway_data' => $result->gatewayResponse,
        ]);

        if ($payload->trialDays) {
            $subscription->update(['status' => SubscriptionStatus::Trialing]);
        }

        SubscriptionCreated::dispatch(
            $subscription->id,
            $gateway,
            $payload->plan->id,
            $payload->plan->money->amount,
            $payload->plan->money->currency,
            $result->gatewayReference,
            $payload->customer?->email,
            $payload->metadata,
        );

        return $result;
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(string $subscriptionId, bool $immediately = false): GatewayResult
    {
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRecurring) {
            throw new UnsupportedCapabilityException($gateway, 'recurring');
        }

        $subscription = Subscription::findOrFail($subscriptionId);
        $credentials = $this->resolveCredentials($gateway);
        $result = $driver->cancelSubscription($subscription->gateway_subscription_id, $credentials);

        if ($result->isSuccessful()) {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'ends_at' => $immediately ? now() : $subscription->current_period_end,
            ]);

            SubscriptionCancelled::dispatch($subscriptionId, $gateway, null, $immediately);
        }

        return $result;
    }

    /**
     * Pause a subscription.
     */
    public function pause(string $subscriptionId): GatewayResult
    {
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRecurring) {
            throw new UnsupportedCapabilityException($gateway, 'recurring');
        }

        $subscription = Subscription::findOrFail($subscriptionId);
        $credentials = $this->resolveCredentials($gateway);
        $result = $driver->pauseSubscription($subscription->gateway_subscription_id, $credentials);

        if ($result->isSuccessful()) {
            $subscription->update([
                'status' => SubscriptionStatus::Paused,
                'paused_at' => now(),
            ]);

            SubscriptionPaused::dispatch($subscriptionId, $gateway);
        }

        return $result;
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(string $subscriptionId): GatewayResult
    {
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRecurring) {
            throw new UnsupportedCapabilityException($gateway, 'recurring');
        }

        $subscription = Subscription::findOrFail($subscriptionId);
        $credentials = $this->resolveCredentials($gateway);
        $result = $driver->resumeSubscription($subscription->gateway_subscription_id, $credentials);

        if ($result->isSuccessful()) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'paused_at' => null,
            ]);

            SubscriptionResumed::dispatch($subscriptionId, $gateway);
        }

        return $result;
    }

    /**
     * Update a subscription (plan change, quantity, etc).
     */
    public function update(string $subscriptionId, array $changes): GatewayResult
    {
        $gateway = $this->resolveGatewayName();
        $driver = $this->resolveDriver($gateway);

        if (! $driver instanceof SupportsRecurring) {
            throw new UnsupportedCapabilityException($gateway, 'recurring');
        }

        $subscription = Subscription::findOrFail($subscriptionId);
        $credentials = $this->resolveCredentials($gateway);
        $result = $driver->updateSubscription($subscription->gateway_subscription_id, $changes, $credentials);

        if ($result->isSuccessful()) {
            $updateData = [];
            if (isset($changes['plan_id'])) {
                $updateData['plan_id'] = $changes['plan_id'];
            }
            if (isset($changes['quantity'])) {
                $updateData['quantity'] = $changes['quantity'];
            }
            if (! empty($updateData)) {
                $subscription->update($updateData);
            }
        }

        return $result;
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
