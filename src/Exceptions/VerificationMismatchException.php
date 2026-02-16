<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class VerificationMismatchException extends RuntimeException
{
    public function __construct(string $message = 'Payment verification mismatch.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
