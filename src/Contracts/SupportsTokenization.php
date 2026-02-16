<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsTokenization
{
    /**
     * Tokenize a payment method for future use.
     */
    public function tokenize(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Charge a previously saved token.
     */
    public function chargeToken(string $token, CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Delete a stored token from the gateway.
     */
    public function deleteToken(string $token, CredentialsDTO $credentials): GatewayResult;

    /**
     * List saved payment methods for a customer.
     */
    public function listTokens(string $customerId, CredentialsDTO $credentials): array;
}
