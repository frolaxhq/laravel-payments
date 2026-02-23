<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Update the payment record status from the gateway result.
 */
class UpdatePaymentStatus
{
    public function __construct(
        protected PaymentConfig $config,
        protected PaymentLoggerContract $logger,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        if (! $this->config->shouldPersistPayments()) {
            return $next($context);
        }

        if ($context->exception) {
            PaymentModel::where('id', $context->paymentId)->update([
                'status' => PaymentStatus::Failed->value,
            ]);

            $this->logger->error('payment.create.failed', "Payment creation failed: {$context->exception->getMessage()}", [
                'payment' => ['id' => $context->paymentId],
                'gateway' => ['name' => $context->gateway],
                'error' => ['message' => $context->exception->getMessage()],
            ]);
        } elseif ($context->result) {
            PaymentModel::where('id', $context->paymentId)->update([
                'status' => $context->result->status->value,
                'gateway_reference' => $context->result->gatewayReference,
            ]);

            $this->logger->info('payment.created', "Payment [{$context->paymentId}] created successfully via [{$context->gateway}]", [
                'payment' => ['id' => $context->paymentId],
                'gateway' => ['name' => $context->gateway, 'reference' => $context->result->gatewayReference],
                'result' => ['status' => $context->result->status->value],
                'timing' => ['duration_ms' => $context->elapsedMs],
            ]);
        }

        return $next($context);
    }
}
