<?php

namespace Frolax\Payment\Data;

use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\Enums\SubscriptionStatus;
use Frolax\Payment\Enums\WebhookEventType;

final readonly class WebhookData
{
    public function __construct(
        public WebhookEventType $canonicalEvent,
        public string $gateway,
        public ?string $gatewayEventType = null,
        public ?string $gatewayReference = null,
        public ?string $paymentId = null,
        public ?string $subscriptionId = null,
        public ?string $customerId = null,
        public ?string $invoiceId = null,
        public ?string $refundId = null,
        public ?Money $amount = null,
        public ?PaymentStatus $paymentStatus = null,
        public ?SubscriptionStatus $subscriptionStatus = null,
        public array $metadata = [],
        public array $rawPayload = [],
    ) {}

    /**
     * Determine if this webhook event relates to a payment.
     */
    public function isPaymentEvent(): bool
    {
        return $this->canonicalEvent->isPaymentEvent();
    }

    /**
     * Determine if this webhook event relates to a subscription.
     */
    public function isSubscriptionEvent(): bool
    {
        return $this->canonicalEvent->isSubscriptionEvent();
    }

    /**
     * Determine if this webhook event relates to an invoice.
     */
    public function isInvoiceEvent(): bool
    {
        return $this->canonicalEvent->isInvoiceEvent();
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return array_filter([
            'canonical_event' => $this->canonicalEvent->value,
            'gateway' => $this->gateway,
            'gateway_event_type' => $this->gatewayEventType,
            'gateway_reference' => $this->gatewayReference,
            'payment_id' => $this->paymentId,
            'subscription_id' => $this->subscriptionId,
            'customer_id' => $this->customerId,
            'invoice_id' => $this->invoiceId,
            'refund_id' => $this->refundId,
            'amount' => $this->amount?->toArray(),
            'payment_status' => $this->paymentStatus?->value,
            'subscription_status' => $this->subscriptionStatus?->value,
            'metadata' => $this->metadata ?: null,
        ], fn ($v) => $v !== null);
    }
}
