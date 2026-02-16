<?php

namespace Frolax\Payment\Contracts;

/**
 * Future capability: tokenization for saved payment methods.
 */
interface SupportsTokenization
{
    /**
     * Tokenize a payment method for future use.
     */
    public function tokenize(array $paymentMethodData, \Frolax\Payment\DTOs\CredentialsDTO $credentials): array;

    /**
     * Charge using a previously tokenized payment method.
     */
    public function chargeToken(string $token, \Frolax\Payment\DTOs\CanonicalPayload $payload, \Frolax\Payment\DTOs\CredentialsDTO $credentials): \Frolax\Payment\DTOs\GatewayResult;
}
