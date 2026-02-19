<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsCOD
{
    public function createCODOrder(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    public function confirmCODDelivery(string $orderId, CredentialsDTO $credentials): GatewayResult;
}
