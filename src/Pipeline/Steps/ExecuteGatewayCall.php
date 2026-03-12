<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Execute the actual gateway driver call and measure timing.
 * Exceptions are stored in context, never rethrown here.
 */
class ExecuteGatewayCall
{
    public function __construct(
        protected PaymentLoggerContract $logger,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        $this->logger->info('payment.create', "Creating payment for order [{$context->payload->order->id}] via [{$context->gateway}]", [
            'gateway' => ['name' => $context->gateway],
            'payment' => ['order' => ['id' => $context->payload->order->id], 'money' => $context->payload->money->toArray()],
            'idempotency_key' => $context->payload->idempotencyKey,
        ]);

        $startTime = microtime(true);
        $newContext = $context->withAttemptNo(1);

        try {
            $result = $newContext->driver->create($newContext->payload, $newContext->credentials);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $newContext = $newContext
                ->withResult($result)
                ->withTiming($startTime, $elapsedMs);
        } catch (\Throwable $e) {
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $newContext = $newContext
                ->withException($e)
                ->withTiming($startTime, $elapsedMs);
        }

        return $next($newContext);
    }
}
