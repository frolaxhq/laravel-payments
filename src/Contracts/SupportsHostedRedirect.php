<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\GatewayResult;

interface SupportsHostedRedirect
{
    /**
     * The create() result should include a redirect_url.
     * This marker interface indicates the gateway uses hosted checkout.
     */
    public function getRedirectUrl(GatewayResult $result): ?string;
}
