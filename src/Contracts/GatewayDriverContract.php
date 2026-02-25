<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;
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
    public function create(Payload $payload, Credentials $credentials): GatewayResult;

    /**
     * Verify a payment from a gateway callback/return.
     */
    public function verify(Request $request, Credentials $credentials): GatewayResult;

    /**
     * Set credentials for this driver instance.
     */
    public function setCredentials(Credentials $credentials): static;
}
