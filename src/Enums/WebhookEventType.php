<?php

namespace Frolax\Payment\Enums;

enum WebhookEventType: string
{
    case PaymentCompleted = 'payment.completed';
    case PaymentFailed = 'payment.failed';
    case PaymentPending = 'payment.pending';
    case PaymentExpired = 'payment.expired';
    case PaymentRefunded = 'payment.refunded';

    case SubscriptionCreated = 'subscription.created';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionCancelled = 'subscription.cancelled';
    case SubscriptionPaused = 'subscription.paused';
    case SubscriptionResumed = 'subscription.resumed';
    case SubscriptionRenewed = 'subscription.renewed';
    case SubscriptionExpired = 'subscription.expired';
    case SubscriptionTrialEnding = 'subscription.trial_ending';

    case InvoicePaid = 'invoice.paid';
    case InvoiceFailed = 'invoice.failed';

    case DisputeCreated = 'dispute.created';

    case Unknown = 'unknown';

    /**
     * Determine if this event type relates to a payment.
     */
    public function isPaymentEvent(): bool
    {
        return in_array($this, [
            self::PaymentCompleted,
            self::PaymentFailed,
            self::PaymentPending,
            self::PaymentExpired,
            self::PaymentRefunded,
        ]);
    }

    /**
     * Determine if this event type relates to a subscription.
     */
    public function isSubscriptionEvent(): bool
    {
        return in_array($this, [
            self::SubscriptionCreated,
            self::SubscriptionActivated,
            self::SubscriptionCancelled,
            self::SubscriptionPaused,
            self::SubscriptionResumed,
            self::SubscriptionRenewed,
            self::SubscriptionExpired,
            self::SubscriptionTrialEnding,
        ]);
    }

    /**
     * Determine if this event type relates to an invoice.
     */
    public function isInvoiceEvent(): bool
    {
        return in_array($this, [
            self::InvoicePaid,
            self::InvoiceFailed,
        ]);
    }
}
