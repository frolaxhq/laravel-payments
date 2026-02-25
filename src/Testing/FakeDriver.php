<?php

namespace Frolax\Payment\Testing;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Contracts\SupportsStatusQuery;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Data\RefundPayload;
use Frolax\Payment\Data\StatusPayload;
use Frolax\Payment\Data\SubscriptionPayload;
use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;

/**
 * A universal fake driver for testing.
 *
 * Records all calls and can be configured to return specific results.
 */
class FakeDriver implements GatewayDriverContract, SupportsRecurring, SupportsRefund, SupportsStatusQuery
{
    protected ?GatewayResult $pendingResult = null;

    /** @var array<array{method: string, args: array}> */
    protected array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function willReturn(GatewayResult $result): static
    {
        $this->pendingResult = $result;

        return $this;
    }

    public function willSucceed(string $gatewayReference = 'FAKE-REF-001'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Completed,
            gatewayReference: $gatewayReference,
        ));
    }

    public function willRedirectTo(string $url, string $gatewayReference = 'FAKE-REF-001'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: $gatewayReference,
            redirectUrl: $url,
        ));
    }

    public function willFail(string $errorMessage = 'Fake payment failed'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Failed,
            errorMessage: $errorMessage,
        ));
    }

    public function alwaysFail(string $errorMessage = 'Fake payment failed'): static
    {
        $this->willFail($errorMessage);

        return $this;
    }

    public function create(Payload $payload, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'create', 'args' => compact('payload', 'credentials')];

        return $this->resolveResult();
    }

    public function verify(Request $request, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'verify', 'args' => compact('request', 'credentials')];

        return $this->resolveResult();
    }

    public function status(StatusPayload $payload, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'status', 'args' => compact('payload', 'credentials')];

        return $this->resolveResult();
    }

    public function refund(RefundPayload $payload, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'refund', 'args' => compact('payload', 'credentials')];

        return $this->resolveResult();
    }

    public function createSubscription(SubscriptionPayload $payload, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'createSubscription', 'args' => compact('payload', 'credentials')];

        return $this->resolveResult();
    }

    public function updateSubscription(string $subscriptionId, array $data, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'updateSubscription', 'args' => compact('subscriptionId', 'data', 'credentials')];

        return $this->resolveResult();
    }

    public function pauseSubscription(string $subscriptionId, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'pauseSubscription', 'args' => compact('subscriptionId', 'credentials')];

        return $this->resolveResult();
    }

    public function resumeSubscription(string $subscriptionId, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'resumeSubscription', 'args' => compact('subscriptionId', 'credentials')];

        return $this->resolveResult();
    }

    public function cancelSubscription(string $subscriptionId, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'cancelSubscription', 'args' => compact('subscriptionId', 'credentials')];

        return $this->resolveResult();
    }

    public function getSubscriptionStatus(string $subscriptionId, Credentials $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'getSubscriptionStatus', 'args' => compact('subscriptionId', 'credentials')];

        return $this->resolveResult();
    }

    public function setCredentials(Credentials $credentials): static
    {
        return $this;
    }

    public function calls(): array
    {
        return $this->calls;
    }

    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($call) => $call['method'] === $method));
    }

    public function assertCalledTimes(string $method, int $times): void
    {
        $actual = count($this->callsTo($method));

        \PHPUnit\Framework\Assert::assertSame(
            $times,
            $actual,
            "Expected [{$method}] to be called [{$times}] times, got [{$actual}]."
        );
    }

    public function assertNotCalled(string $method): void
    {
        $this->assertCalledTimes($method, 0);
    }

    protected function resolveResult(): GatewayResult
    {
        if ($this->pendingResult) {
            $result = $this->pendingResult;
            $this->pendingResult = null;

            return $result;
        }

        return new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: 'FAKE-REF-'.count($this->calls),
        );
    }
}
