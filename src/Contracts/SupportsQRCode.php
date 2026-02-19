<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsQRCode
{
    public function generateQRCode(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    public function verifyQRPayment(string $reference, CredentialsDTO $credentials): GatewayResult;
}
