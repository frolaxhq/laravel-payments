<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Execute the actual gateway driver call and measure timing.
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

        $context->attemptNo = 1;
        $context->startTime = microtime(true);

        try {
            $context->result = $context->driver->create($context->payload, $context->credentials);
            $context->elapsedMs = round((microtime(true) - $context->startTime) * 1000, 2);
        } catch (\Throwable $e) {
            $context->elapsedMs = round((microtime(true) - $context->startTime) * 1000, 2);
            $context->exception = $e;
        }

        return $next($context);
    }
}
