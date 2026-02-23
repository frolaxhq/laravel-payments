<?php

namespace Frolax\Payment\Testing;

use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\DTOs\CanonicalPayload;
use Frolax\Payment\DTOs\CredentialsDTO;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Illuminate\Http\Request;

/**
 * A universal fake driver for testing.
 *
 * Records all calls and can be configured to return specific results.
 */
class FakeDriver implements GatewayDriverContract
{
    protected ?GatewayResult $pendingResult = null;

    /** @var array<array{method: string, args: array}> */
    protected array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    /**
     * Configure the result that will be returned for the next call.
     */
    public function willReturn(GatewayResult $result): static
    {
        $this->pendingResult = $result;

        return $this;
    }

    /**
     * Shortcut: configure a successful result.
     */
    public function willSucceed(string $gatewayReference = 'FAKE-REF-001'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Completed,
            gatewayReference: $gatewayReference,
        ));
    }

    /**
     * Shortcut: configure a pending/redirect result.
     */
    public function willRedirectTo(string $url, string $gatewayReference = 'FAKE-REF-001'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: $gatewayReference,
            redirectUrl: $url,
        ));
    }

    /**
     * Shortcut: configure a failing result.
     */
    public function willFail(string $errorMessage = 'Fake payment failed'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Failed,
            errorMessage: $errorMessage,
        ));
    }

    public function create(CanonicalPayload $payload, CredentialsDTO $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'create', 'args' => compact('payload', 'credentials')];

        return $this->resolveResult();
    }

    public function verify(Request $request, CredentialsDTO $credentials): GatewayResult
    {
        $this->calls[] = ['method' => 'verify', 'args' => compact('request', 'credentials')];

        return $this->resolveResult();
    }

    public function setCredentials(CredentialsDTO $credentials): static
    {
        return $this;
    }

    /**
     * Get all recorded calls.
     *
     * @return array<array{method: string, args: array}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Get calls for a specific method.
     */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($call) => $call['method'] === $method));
    }

    /**
     * Assert that a method was called a specific number of times.
     */
    public function assertCalledTimes(string $method, int $times): void
    {
        $actual = count($this->callsTo($method));

        \PHPUnit\Framework\Assert::assertSame(
            $times,
            $actual,
            "Expected [{$method}] to be called [{$times}] times, got [{$actual}]."
        );
    }

    /**
     * Assert that a method was never called.
     */
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
