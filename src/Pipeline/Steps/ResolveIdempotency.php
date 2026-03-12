<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Check if a payment with this idempotency key already exists.
 * If so, short-circuit and return the existing result for ANY status
 * (including pending) — prevents duplicate DB inserts.
 */
class ResolveIdempotency
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

        // Short-circuit on ALL statuses, including pending.
        // The original bug only short-circuited on non-pending, allowing
        // duplicate DB inserts when a pending payment existed.
        if ($existing) {
            return $context->withResult(new GatewayResult(
                status: $existing->status,
                gatewayReference: $existing->gateway_reference,
            ));
        }

        return $next($context);
    }
}
