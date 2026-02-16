<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalStatusPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsStatusQuery
{
    /**
     * Query the current status of a payment.
     */
    public function status(CanonicalStatusPayload $payload, CredentialsDTO $credentials): GatewayResult;
}
