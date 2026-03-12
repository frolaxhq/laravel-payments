<?php

namespace Frolax\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Cancelled,
            self::Refunded,
            self::Expired,
        ]);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(self $next): bool
    {
        /** @var array<string, list<self>> $allowed */
        $allowed = [
            self::Pending->value => [self::Processing, self::Completed, self::Failed, self::Cancelled, self::Expired],
            self::Processing->value => [self::Completed, self::Failed, self::Cancelled],
            self::Completed->value => [self::Refunded, self::PartiallyRefunded],
            self::Failed->value => [],
            self::Cancelled->value => [],
            self::Refunded->value => [],
            self::PartiallyRefunded->value => [self::Refunded],
            self::Expired->value => [],
        ];

        return in_array($next, $allowed[$this->value] ?? [], true);
    }
}
