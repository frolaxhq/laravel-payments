<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;

interface SupportsPayout
{
    /**
     * Create a payout to a recipient.
     */
    public function createPayout(array $payoutData, CredentialsDTO $credentials): GatewayResult;

    /**
     * Split a payment among multiple recipients.
     */
    public function splitPayment(array $splitRules, CredentialsDTO $credentials): GatewayResult;

    /**
     * Get payout status.
     */
    public function getPayoutStatus(string $payoutId, CredentialsDTO $credentials): GatewayResult;
}
