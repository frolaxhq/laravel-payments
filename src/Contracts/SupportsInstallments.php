<?php

namespace Frolax\Payment\Contracts;

/**
 * Future capability: installment/EMI payment plans.
 */
interface SupportsInstallments
{
    /**
     * Get available installment plans for a given amount.
     */
    public function getInstallmentPlans(\Frolax\Payment\DTOs\MoneyDTO $money, \Frolax\Payment\DTOs\CredentialsDTO $credentials): array;
}
