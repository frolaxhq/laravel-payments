<?php

namespace Frolax\Payment\Exceptions;

use RuntimeException;

class MissingCredentialsException extends RuntimeException
{
    public function __construct(string $gateway, string $profile, ?string $tenantId = null)
    {
        $msg = "Missing credentials for gateway [{$gateway}] profile [{$profile}]";
        if ($tenantId) {
            $msg .= " tenant [{$tenantId}]";
        }
        $msg .= '.';

        parent::__construct($msg);
    }
}
