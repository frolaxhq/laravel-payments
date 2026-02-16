<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $gateway,
        public readonly ?string $eventType = null,
        public readonly ?string $gatewayReference = null,
        public readonly bool $signatureValid = false,
        public readonly array $payload = [],
        public readonly array $headers = [],
    ) {}
}
