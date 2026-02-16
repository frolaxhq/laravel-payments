<?php

namespace Frolax\Payment\Enums;

enum LogLevel: string
{
    case Off = 'off';
    case ErrorsOnly = 'errors_only';
    case Basic = 'basic';
    case Verbose = 'verbose';
    case Debug = 'debug';

    public function priority(): int
    {
        return match ($this) {
            self::Off => 0,
            self::ErrorsOnly => 1,
            self::Basic => 2,
            self::Verbose => 3,
            self::Debug => 4,
        };
    }

    public function allows(self $level): bool
    {
        return $this->priority() >= $level->priority();
    }
}
