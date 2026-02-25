<?php

namespace Frolax\Payment\Testing;

use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Payment;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;

/**
 * Testing fake for the Payment manager.
 *
 * Usage:
 *   Payment::fake();
 *   // ...run code that charges...
 *   Payment::assertCharged(fn ($data) => $data['money']['amount'] === 100);
 *   Payment::assertGatewayUsed('stripe');
 */
class PaymentFake extends Payment
{
    /** @var array<array{gateway: string, method: string, data: array, result: GatewayResult}> */
    protected static array $recorded = [];

    protected ?GatewayResult $pendingResult = null;

    /**
     * Configure a fake result for the next operation.
     */
    public function willReturn(GatewayResult $result): static
    {
        $this->pendingResult = $result;

        return $this;
    }

    /**
     * Shortcut: next charge/subscribe/refund will succeed.
     */
    public function willSucceed(string $gatewayReference = 'FAKE-REF-001'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Completed,
            gatewayReference: $gatewayReference,
        ));
    }

    /**
     * Shortcut: next charge will redirect.
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
     * Shortcut: next operation will fail.
     */
    public function willFail(string $errorMessage = 'Fake payment failed'): static
    {
        return $this->willReturn(new GatewayResult(
            status: PaymentStatus::Failed,
            errorMessage: $errorMessage,
        ));
    }

    public function charge(array $data): GatewayResult
    {
        $result = $this->resolveResult();
        self::$recorded[] = [
            'gateway' => $this->resolveGatewayName(),
            'method' => 'charge',
            'data' => $data,
            'result' => $result,
        ];

        return $result;
    }

    public function create(array $data): GatewayResult
    {
        return $this->charge($data);
    }

    public function subscribe(array $data): GatewayResult
    {
        $result = $this->resolveResult();
        self::$recorded[] = [
            'gateway' => $this->resolveGatewayName(),
            'method' => 'subscribe',
            'data' => $data,
            'result' => $result,
        ];

        return $result;
    }

    public function refund(array $data): GatewayResult
    {
        $result = $this->resolveResult();
        self::$recorded[] = [
            'gateway' => $this->resolveGatewayName(),
            'method' => 'refund',
            'data' => $data,
            'result' => $result,
        ];

        return $result;
    }

    public function verifyFromRequest(Request $request): GatewayResult
    {
        $result = $this->resolveResult();
        self::$recorded[] = [
            'gateway' => $this->resolveGatewayName(),
            'method' => 'verify',
            'data' => $request->all(),
            'result' => $result,
        ];

        return $result;
    }

    // -------------------------------------------------------
    // Assertion Helpers
    // -------------------------------------------------------

    /**
     * Assert a charge was made, optionally matching a callback.
     */
    public function assertCharged(?callable $callback = null): void
    {
        $this->assertRecordedMethod('charge', $callback);
    }

    /**
     * Assert a subscription was created, optionally matching a callback.
     */
    public function assertSubscribed(?callable $callback = null): void
    {
        $this->assertRecordedMethod('subscribe', $callback);
    }

    /**
     * Assert a refund was made, optionally matching a callback.
     */
    public function assertRefunded(?callable $callback = null): void
    {
        $this->assertRecordedMethod('refund', $callback);
    }

    /**
     * Assert no charges were made.
     */
    public function assertNothingCharged(): void
    {
        $charges = $this->recordsFor('charge');
        Assert::assertEmpty($charges, 'Expected no charges but found '.count($charges).'.');
    }

    /**
     * Assert a specific gateway was used.
     */
    public function assertGatewayUsed(string $gateway): void
    {
        $used = array_filter(self::$recorded, fn ($r) => $r['gateway'] === $gateway);
        Assert::assertNotEmpty($used, "Expected gateway [{$gateway}] to be used, but it was not.");
    }

    /**
     * Assert a specific gateway was never used.
     */
    public function assertGatewayNotUsed(string $gateway): void
    {
        $used = array_filter(self::$recorded, fn ($r) => $r['gateway'] === $gateway);
        Assert::assertEmpty($used, "Expected gateway [{$gateway}] not to be used, but it was.");
    }

    /**
     * Get all recorded operations.
     */
    public function recorded(): array
    {
        return self::$recorded;
    }

    // -------------------------------------------------------
    // Internal
    // -------------------------------------------------------

    protected function assertRecordedMethod(string $method, ?callable $callback): void
    {
        $records = $this->recordsFor($method);

        Assert::assertNotEmpty($records, "Expected [{$method}] to be called, but it was not.");

        if ($callback) {
            $matched = array_filter($records, fn ($r) => $callback($r['data'], $r['result']));
            Assert::assertNotEmpty($matched, "Expected [{$method}] matching the callback, but none matched.");
        }
    }

    protected function recordsFor(string $method): array
    {
        return array_values(array_filter(self::$recorded, fn ($r) => $r['method'] === $method));
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
            gatewayReference: 'FAKE-REF-'.count(self::$recorded),
        );
    }
}
