<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsBankTransfer
{
    public function initiateBankTransfer(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function verifyBankTransfer(string $transferReference, CredentialsDTO $credentials): GatewayResult;
}
