<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class MissingCredentialsException extends RuntimeException
{
    public function __construct(string $gateway, string $profile)
    {
        $msg = "Missing credentials for gateway [{$gateway}] profile [{$profile}].";

        parent::__construct($msg);
    }
}
