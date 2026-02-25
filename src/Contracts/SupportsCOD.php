<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;

interface SupportsCOD
{
    public function createCODOrder(Payload $payload, Credentials $credentials): GatewayResult;

    public function confirmCODDelivery(string $orderId, Credentials $credentials): GatewayResult;
}
