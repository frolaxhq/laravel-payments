<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class UnsupportedCapabilityException extends RuntimeException
{
    public function __construct(string $gateway, string $capability)
    {
        parent::__construct("Gateway [{$gateway}] does not support [{$capability}].");
    }
}
