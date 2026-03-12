<?php

namespace Frolax\Payment\Drivers;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Built-in dummy driver for testing and development.
 *
 * Always returns successful results with deterministic references.
 * No external HTTP calls are made.
 */
class DummyDriver implements GatewayDriverContract
{
    protected ?Credentials $credentials = null;

    public function name(): string
    {
        return 'dummy';
    }

    public function create(Payload $payload, Credentials $credentials): GatewayResult
    {
        return new GatewayResult(
            status: PaymentStatus::Completed,
            gatewayReference: 'DUMMY-'.(string) Str::ulid(),
            gatewayResponse: [
                'driver' => 'dummy',
                'order_id' => $payload->order->id,
                'amount' => $payload->money->amount,
                'currency' => $payload->money->currency,
            ],
        );
    }

    public function verify(Request $request, Credentials $credentials): GatewayResult
    {
        return new GatewayResult(
            status: PaymentStatus::Completed,
            gatewayReference: $request->input('gateway_reference', 'DUMMY-VERIFY'),
            gatewayResponse: ['verified' => true],
        );
    }

    public function setCredentials(Credentials $credentials): GatewayDriverContract
    {
        $this->credentials = $credentials;

        return $this;
    }
}
