<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class InvalidCanonicalPayloadException extends RuntimeException
{
    protected array $errors;

    public function __construct(string $message = 'Invalid canonical payload.', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
