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
     * Parse the webhook request into a canonical WebhookData DTO.
     */
    public function parseWebhookData(Request $request): WebhookData;

    /**
     * Process a push webhook payload and return canonical WebhookData.
     *
     * This is the method called by the WebhookController for push webhooks.
     * It should parse the payload, update any internal state, and return
     * a WebhookData DTO representing the webhook event.
     *
     * This is separate from verify() which handles return-URL callbacks.
     */
    public function handleWebhook(Request $request, Credentials $credentials): WebhookData;
}
