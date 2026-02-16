<?php

namespace Frolax\Payment\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Incomplete = 'incomplete';

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Trialing]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Active, self::Trialing, self::PastDue, self::Paused]);
    }

    public function canBePaused(): bool
    {
        return in_array($this, [self::Active, self::Trialing]);
    }

    public function canBeResumed(): bool
    {
        return $this === self::Paused;
    }
}
