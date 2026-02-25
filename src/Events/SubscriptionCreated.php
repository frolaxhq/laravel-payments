<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $gateway,
        public readonly string $priceId,
        public readonly string $planId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $gatewaySubscriptionId = null,
        public readonly ?string $customerEmail = null,
        public readonly array $metadata = [],
    ) {}
}
