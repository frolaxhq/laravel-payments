<?php

namespace Frolax\Payment\Contracts;

use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\WebhookData;
use Illuminate\Http\Request;

interface SupportsWebhookVerification
{
    /**
     * Verify a webhook signature/payload is authentic.
     */
    public function verifyWebhookSignature(Request $request, Credentials $credentials): bool;

    /**
     * Parse the webhook event type from the request.
     */
    public function parseWebhookEventType(Request $request): ?string;

    /**
     * Parse the gateway reference from the webhook request.
     */
    public function parseWebhookGatewayReference(Request $request): ?string;

    /**
     * Parse the webhook request into a canonical WebhookData DTO.
     */
    public function parseWebhookData(Request $request): WebhookData;
}
