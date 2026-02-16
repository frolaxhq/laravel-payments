<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsWallets
{
    public function createWalletCharge(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;
    public function getWalletBalance(string $walletId, CredentialsDTO $credentials): GatewayResult;
}
