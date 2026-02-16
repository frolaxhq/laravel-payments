<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class InvalidSignatureException extends RuntimeException
{
    public function __construct(string $message = 'Invalid webhook signature.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
