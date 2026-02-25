<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\SubscriptionPayload;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;

interface SupportsRecurring
{
    /**
     * Create a subscription through the gateway.
     */
    public function createSubscription(SubscriptionPayload $payload, Credentials $credentials): GatewayResult;

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(string $subscriptionId, Credentials $credentials): GatewayResult;

    /**
     * Pause an active subscription.
     */
    public function pauseSubscription(string $subscriptionId, Credentials $credentials): GatewayResult;

    /**
     * Resume a paused subscription.
     */
    public function resumeSubscription(string $subscriptionId, Credentials $credentials): GatewayResult;

    /**
     * Update a subscription (plan change, quantity, etc).
     */
    public function updateSubscription(string $subscriptionId, array $changes, Credentials $credentials): GatewayResult;

    /**
     * Get the current status of a subscription from the gateway.
     */
    public function getSubscriptionStatus(string $subscriptionId, Credentials $credentials): GatewayResult;
}
