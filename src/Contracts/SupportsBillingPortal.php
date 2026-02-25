<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;

interface SupportsBillingPortal
{
    /**
     * Create a billing portal session for a customer.
     *
     * Returns a GatewayResult with a redirect URL to the gateway's
     * hosted billing portal where customers can manage subscriptions,
     * payment methods, and invoices.
     */
    public function createBillingPortalSession(
        string $customerId,
        Credentials $credentials,
        ?string $returnUrl = null,
    ): GatewayResult;
}
