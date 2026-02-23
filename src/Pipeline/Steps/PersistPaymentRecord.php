<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;
use Illuminate\Support\Str;

/**
 * Create the initial payment record in the database.
 */
class PersistPaymentRecord
{
    public function __construct(
        protected PaymentConfig $config,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        $context->paymentId = (string) Str::ulid();

        if ($this->config->shouldPersistPayments()) {
            PaymentModel::create([
                'id' => $context->paymentId,
                'order_id' => $context->payload->order->id,
                'gateway_name' => $context->gateway,
                'profile' => $context->profile,
                'tenant_id' => $context->tenantId,
                'status' => PaymentStatus::Pending->value,
                'amount' => $context->payload->money->amount,
                'currency' => $context->payload->money->currency,
                'idempotency_key' => $context->payload->idempotencyKey,
                'customer_email' => $context->payload->customer?->email,
                'customer_phone' => $context->payload->customer?->phone,
                'canonical_payload' => $context->payload->toArray(),
                'metadata' => $context->payload->metadata,
            ]);
        }

        return $next($context);
    }
}
