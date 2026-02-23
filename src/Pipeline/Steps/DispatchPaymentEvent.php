<?php

namespace Frolax\Payment\Pipeline\Steps;

use Closure;
use Frolax\Payment\Events\PaymentCreated;
use Frolax\Payment\Events\PaymentFailed;
use Frolax\Payment\Pipeline\PaymentContext;

/**
 * Dispatch the appropriate event after payment processing.
 *
 * If an exception occurred, dispatches PaymentFailed and re-throws.
 * Otherwise, dispatches PaymentCreated.
 */
class DispatchPaymentEvent
{
    public function handle(PaymentContext $context, Closure $next): mixed
    {
        if ($context->exception) {
            event(new PaymentFailed(
                paymentId: $context->paymentId ?? '',
                gateway: $context->gateway,
                errorMessage: $context->exception->getMessage(),
            ));

            throw $context->exception;
        }

        if ($context->result) {
            event(new PaymentCreated(
                paymentId: $context->paymentId ?? '',
                gateway: $context->gateway,
                orderId: $context->payload->order->id,
                amount: $context->payload->money->amount,
                currency: $context->payload->money->currency,
                redirectUrl: $context->result->redirectUrl,
                metadata: $context->payload->metadata,
            ));
        }

        return $next($context);
    }
}
