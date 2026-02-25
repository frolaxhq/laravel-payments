<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\StatusPayload;

interface SupportsStatusQuery
{
    /**
     * Query the current status of a payment.
     */
    public function status(StatusPayload $payload, Credentials $credentials): GatewayResult;
}
