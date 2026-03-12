<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create the initial payment record in the database.
 * Wrapped in DB::transaction() for atomicity.
 */
class PersistPaymentRecord
{
    public function __construct(
        protected PaymentConfig $config,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        $paymentId = (string) Str::ulid();
        $newContext = $context->withPaymentId($paymentId);

        if ($this->config->shouldPersistPayments()) {
            DB::transaction(function () use ($newContext) {
                PaymentModel::create([
                    'id' => $newContext->paymentId,
                    'order_id' => $newContext->payload->order->id,
                    'gateway_name' => $newContext->gateway,
                    'profile' => $newContext->profile,
                    'status' => PaymentStatus::Pending->value,
                    'amount' => $newContext->payload->money->amount,
                    'currency' => $newContext->payload->money->currency,
                    'idempotency_key' => $newContext->payload->idempotencyKey,
                    'customer_email' => $newContext->payload->customer?->email,
                    'customer_phone' => $newContext->payload->customer?->phone,
                    'canonical_payload' => $newContext->payload->toArray(),
                    'metadata' => $newContext->payload->metadata,
                ]);
            });
        }

        return $next($newContext);
    }
}
