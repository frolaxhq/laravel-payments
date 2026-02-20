<?php

namespace Frolax\Payment\Facades;

use Frolax\Payment\DTOs\GatewayResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Frolax\Payment\Payment gateway(?string $name = null)
 * @method static \Frolax\Payment\Payment usingContext(array $context)
 * @method static \Frolax\Payment\Payment withProfile(string $profile)
 * @method static \Frolax\Payment\Payment usingCredentials(array $credentials)
 * @method static GatewayResult create(array $data)
 * @method static GatewayResult verifyFromRequest(\Illuminate\Http\Request $request)
 * @method static GatewayResult status(array $data)
 *
 * @see \Frolax\Payment\Payment
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Frolax\Payment\Payment::class;
    }
}
