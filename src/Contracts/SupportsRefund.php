<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalRefundPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsRefund
{
    /**
     * Process a refund.
     */
    public function refund(CanonicalRefundPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
