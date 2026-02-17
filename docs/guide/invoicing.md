# Invoicing

Generate invoices and credit notes from payments and subscriptions.

## Invoice Generator

```php
use Frolax\Payment\Services\InvoiceGenerator;
use Frolax\Payment\Models\Payment;

$generator = app(InvoiceGenerator::class);

// From a payment
$payment = Payment::find($id);
$invoice = $generator->fromPayment($payment);

// From a subscription
$invoice = $generator->fromSubscription($subscription);

// Issue a credit note
$creditNote = $generator->issueCreditNote($invoice, 50.00, 'Partial refund');
```

## Tax Calculation

```php
use Frolax\Payment\Services\TaxCalculator;

$calculator = app(TaxCalculator::class);

// Calculate taxes for a region
$taxes = $calculator->calculate(100.00, 'US', 'CA');
// Returns: ['tax_amount' => 7.25, 'rates' => [...]]
```

## Models

### Invoice
- `markPaid()`, `markVoid()` — state management
- `items()` — line items relationship
- `creditNotes()` — issued credit notes
- `payment()`, `subscription()` — parent relationships

### TaxRate
- `calculate($amount)` — computes tax for inclusive/exclusive rates
- Scoped by region and active status

## Database Tables

- `payment_invoices` — invoice records with status and totals
- `payment_invoice_items` — individual line items
- `payment_credit_notes` — partial and full credit notes
- `payment_tax_rates` — configurable tax rates by region
