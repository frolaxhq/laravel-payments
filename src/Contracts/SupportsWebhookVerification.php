<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\DTOs\CredentialsDTO;
use Illuminate\Http\Request;

interface SupportsWebhookVerification
{
    /**
     * Verify a webhook signature/payload is authentic.
     */
    public function verifyWebhookSignature(Request $request, CredentialsDTO $credentials): bool;

    /**
     * Parse the webhook event type from the request.
     */
    public function parseWebhookEventType(Request $request): ?string;

    /**
     * Parse the gateway reference from the webhook request.
     */
    public function parseWebhookGatewayReference(Request $request): ?string;
}
