<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\DTOs\GatewayResult;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Check if a payment with this idempotency key already exists.
 * If so, short-circuit and return the existing result.
 */
class CheckIdempotency
{
    public function __construct(
        protected PaymentConfig $config,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        if (! $this->config->shouldPersistPayments()) {
            return $next($context);
        }

        $existing = PaymentModel::where('idempotency_key', $context->payload->idempotencyKey)->first();

        if ($existing && $existing->status !== PaymentStatus::Pending->value) {
            $context->result = new GatewayResult(
                status: PaymentStatus::from($existing->status),
                gatewayReference: $existing->gateway_reference,
            );

            // Short-circuit: return the idempotent result
            return $context;
        }

        return $next($context);
    }
}
