<?php

namespace Frolax\Payment\Enums;

enum BillingInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function toDays(): ?int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
            self::Quarterly => 90,
            self::Yearly => 365,
            self::Custom => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'day',
            self::Weekly => 'week',
            self::Monthly => 'month',
            self::Quarterly => 'quarter',
            self::Yearly => 'year',
            self::Custom => 'custom',
        };
    }
}
