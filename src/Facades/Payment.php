<?php

namespace Frolax\Payment\Facades;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Testing\PaymentFake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Frolax\Payment\Payment gateway(?string $name = null)
 * @method static \Frolax\Payment\Payment usingContext(array $context)
 * @method static \Frolax\Payment\Payment withProfile(string $profile)
 * @method static \Frolax\Payment\Payment usingCredentials(array $credentials)
 * @method static GatewayResult charge(array $data)
 * @method static GatewayResult create(array $data)
 * @method static GatewayResult verifyFromRequest(Request $request)
 * @method static GatewayResult status(array $data)
 * @method static GatewayResult subscribe(array $data)
 * @method static GatewayResult cancelSubscription(string $subscriptionId, bool $immediately = false)
 * @method static GatewayResult pauseSubscription(string $subscriptionId)
 * @method static GatewayResult resumeSubscription(string $subscriptionId)
 * @method static GatewayResult updateSubscription(string $subscriptionId, array $changes)
 * @method static GatewayResult refund(array $data)
 * @method static GatewayDriverContract driver()
 * @method static bool supports(string $capability)
 * @method static array capabilities()
 * @method static array gateways()
 * @method static array gatewaysThatSupport(string $capability)
 *
 * @see \Frolax\Payment\Payment
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Frolax\Payment\Payment::class;
    }

    /**
     * Replace the Payment singleton with a fake for testing.
     */
    public static function fake(): PaymentFake
    {
        $fake = app(PaymentFake::class);

        static::swap($fake);

        return $fake;
    }
}
