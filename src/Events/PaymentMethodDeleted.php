<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMethodDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $paymentMethodId,
        public readonly string $gateway,
        public readonly string $customerId,
    ) {}
}
