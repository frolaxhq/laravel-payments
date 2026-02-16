<?php

namespace Frolax\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $gateway,
        public readonly ?string $reason = null,
        public readonly bool $immediately = false,
    ) {}
}
