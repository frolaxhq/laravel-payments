<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class GatewayRequestFailedException extends RuntimeException
{
    protected array $response;

    public function __construct(string $gateway, string $message = 'Gateway request failed.', array $response = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct("[{$gateway}] {$message}", $code, $previous);
    }

    public function response(): array
    {
        return $this->response;
    }
}
