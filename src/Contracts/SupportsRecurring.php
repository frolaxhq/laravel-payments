<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalSubscriptionPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsRecurring
{
    /**
     * Create a subscription through the gateway.
     */
    public function createSubscription(CanonicalSubscriptionPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult;

    /**
     * Pause an active subscription.
     */
    public function pauseSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult;

    /**
     * Resume a paused subscription.
     */
    public function resumeSubscription(string $subscriptionId, CredentialsDTO $credentials): GatewayResult;

    /**
     * Update a subscription (plan change, quantity, etc).
     */
    public function updateSubscription(string $subscriptionId, array $changes, CredentialsDTO $credentials): GatewayResult;

    /**
     * Get the current status of a subscription from the gateway.
     */
    public function getSubscriptionStatus(string $subscriptionId, CredentialsDTO $credentials): GatewayResult;
}
