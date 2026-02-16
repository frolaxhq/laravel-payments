<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRefundRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentId,
        public readonly string $refundId,
        public readonly string $gateway,
        public readonly int|float $amount,
        public readonly string $currency,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}
}
