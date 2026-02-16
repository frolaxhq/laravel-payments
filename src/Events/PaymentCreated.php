<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $gateway,
        public readonly string $orderId,
        public readonly int|float $amount,
        public readonly string $currency,
        public readonly ?string $redirectUrl = null,
        public readonly array $metadata = [],
    ) {}
}
