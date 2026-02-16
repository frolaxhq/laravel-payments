<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $gateway,
        public readonly array $metadata = [],
    ) {}
}
