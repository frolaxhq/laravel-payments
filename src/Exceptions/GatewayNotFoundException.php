<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class GatewayNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Gateway not found.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
