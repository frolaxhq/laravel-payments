<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $gateway,
        public readonly string $status,
        public readonly ?string $gatewayReference = null,
        public readonly array $metadata = [],
    ) {}
}
