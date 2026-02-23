<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Enums\AttemptStatus;
use Frolax\Payment\Models\PaymentAttempt;
use Frolax\Payment\PaymentConfig;
use Frolax\Payment\Pipeline\PaymentContext;
use Illuminate\Support\Str;

/**
 * Persist the payment attempt (success or error) to the database.
 */
class PersistAttempt
{
    public function __construct(
        protected PaymentConfig $config,
    ) {}

    public function handle(PaymentContext $context, Closure $next): mixed
    {
        if (! $this->config->shouldPersistAttempts()) {
            return $next($context);
        }

        if ($context->exception) {
            PaymentAttempt::create([
                'id' => (string) Str::ulid(),
                'payment_id' => $context->paymentId,
                'attempt_no' => $context->attemptNo ?? 1,
                'status' => AttemptStatus::Error->value,
                'errors' => ['message' => $context->exception->getMessage(), 'code' => $context->exception->getCode()],
                'duration_ms' => $context->elapsedMs,
            ]);
        } elseif ($context->result) {
            PaymentAttempt::create([
                'id' => (string) Str::ulid(),
                'payment_id' => $context->paymentId,
                'attempt_no' => $context->attemptNo ?? 1,
                'status' => $context->result->isSuccessful() ? AttemptStatus::Succeeded->value : AttemptStatus::Sent->value,
                'gateway_reference' => $context->result->gatewayReference,
                'request_payload' => $context->payload->toArray(),
                'response_payload' => $context->result->gatewayResponse,
                'duration_ms' => $context->elapsedMs,
            ]);
        }

        return $next($context);
    }
}
