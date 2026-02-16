<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMethodSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentMethodId,
        public readonly string $gateway,
        public readonly string $customerId,
        public readonly string $type,
        public readonly ?string $last4 = null,
        public readonly ?string $brand = null,
    ) {}
}
