<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Illuminate\Http\Request;

interface SupportsThreeDSecure
{
    /**
     * Initiate 3D Secure authentication flow.
     */
    public function initiate3DS(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult;

    /**
     * Verify the 3D Secure authentication result.
     */
    public function verify3DS(Request $request, CredentialsDTO $credentials): GatewayResult;
}
