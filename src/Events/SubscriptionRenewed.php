<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $gateway,
        public readonly string $planId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
    ) {}
}
