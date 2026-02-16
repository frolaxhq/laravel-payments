<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsBuyNowPayLater
{
    public function createBNPLSession(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function getBNPLPlans(CanonicalPayload $payload, CredentialsDTO $credentials): array;
}
