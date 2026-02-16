<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Illuminate\Http\Request;

interface GatewayDriverContract
{
    /**
     * The unique gateway key (e.g. "stripe", "bkash").
     */
    public function name(): string;

    /**
     * Create a new payment.
     */
    public function create(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Verify a payment from a gateway callback/return.
     */
    public function verify(Request $request, CredentialsDTO $credentials): GatewayResult;

    /**
     * Set credentials for this driver instance.
     */
    public function setCredentials(CredentialsDTO $credentials): static;

    /**
     * List capabilities this driver supports.
     *
     * @return string[]
     */
    public function capabilities(): array;
}
