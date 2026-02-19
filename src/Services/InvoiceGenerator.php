<?php

namespace Frolax\Payment\Services;

use Frolax\Payment\Models\CreditNote;
use Frolax\Payment\Models\Invoice;
use Frolax\Payment\Models\InvoiceItem;
use Frolax\Payment\Models\PaymentModel;
use Frolax\Payment\Models\Subscription;

class InvoiceGenerator
{
    /**
     * Generate an invoice from a payment.
     */
    public function fromPayment(PaymentModel $payment, array $options = []): Invoice
    {
        $invoice = Invoice::create([
            'number' => $this->generateNumber(),
            'payment_id' => $payment->id,
            'customer_name' => $options['customer_name'] ?? null,
            'customer_email' => $options['customer_email'] ?? null,
            'status' => 'paid',
            'currency' => $payment->currency,
            'subtotal' => $payment->amount,
            'tax_amount' => $options['tax_amount'] ?? 0,
            'discount_amount' => $options['discount_amount'] ?? 0,
            'total' => $payment->amount + ($options['tax_amount'] ?? 0) - ($options['discount_amount'] ?? 0),
            'notes' => $options['notes'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'due_at' => $options['due_at'] ?? now(),
            'paid_at' => now(),
        ]);

        // Create default line item from payment
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $options['description'] ?? "Payment {$payment->id}",
            'quantity' => 1,
            'unit_price' => $payment->amount,
            'tax_rate' => 0,
            'total' => $payment->amount,
        ]);

        return $invoice;
    }

    /**
     * Generate an invoice from a subscription renewal.
     */
    public function fromSubscription(Subscription $subscription, array $options = []): Invoice
    {
        $invoice = Invoice::create([
            'number' => $this->generateNumber(),
            'subscription_id' => $subscription->id,
            'customer_name' => $options['customer_name'] ?? null,
            'customer_email' => $subscription->customer_email,
            'status' => 'unpaid',
            'currency' => $subscription->currency,
            'subtotal' => $subscription->amount * ($subscription->quantity ?? 1),
            'tax_amount' => $options['tax_amount'] ?? 0,
            'discount_amount' => $options['discount_amount'] ?? 0,
            'total' => ($subscription->amount * ($subscription->quantity ?? 1))
                + ($options['tax_amount'] ?? 0)
                - ($options['discount_amount'] ?? 0),
            'notes' => $options['notes'] ?? null,
            'metadata' => $options['metadata'] ?? [],
            'due_at' => $options['due_at'] ?? now()->addDays(config('payments.invoicing.due_days', 30)),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => "{$subscription->plan_name} subscription",
            'quantity' => $subscription->quantity ?? 1,
            'unit_price' => $subscription->amount,
            'tax_rate' => 0,
            'total' => $subscription->amount * ($subscription->quantity ?? 1),
        ]);

        return $invoice;
    }

    /**
     * Issue a credit note against an invoice.
     */
    public function issueCreditNote(Invoice $invoice, float $amount, ?string $reason = null): CreditNote
    {
        return CreditNote::create([
            'invoice_id' => $invoice->id,
            'number' => 'CN-'.strtoupper(substr(md5(uniqid()), 0, 8)),
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }

    /**
     * Generate a unique invoice number.
     */
    protected function generateNumber(): string
    {
        $prefix = config('payments.invoicing.prefix', 'INV');
        $sequence = Invoice::count() + 1;

        return sprintf('%s-%s-%06d', $prefix, now()->format('Y'), $sequence);
    }
}
