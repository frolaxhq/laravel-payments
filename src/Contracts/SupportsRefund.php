<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\RefundPayload;

interface SupportsRefund
{
    /**
     * Process a refund.
     */
    public function refund(RefundPayload $payload, Credentials $credentials): GatewayResult;
}
