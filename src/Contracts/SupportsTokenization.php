<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;

interface SupportsTokenization
{
    /**
     * Tokenize a payment method for future use.
     */
    public function tokenize(Payload $payload, Credentials $credentials): GatewayResult;

    /**
     * Charge a previously saved token.
     */
    public function chargeToken(string $token, Payload $payload, Credentials $credentials): GatewayResult;

    /**
     * Delete a stored token from the gateway.
     */
    public function deleteToken(string $token, Credentials $credentials): GatewayResult;

    /**
     * List saved payment methods for a customer.
     */
    public function listTokens(string $customerId, Credentials $credentials): array;
}
