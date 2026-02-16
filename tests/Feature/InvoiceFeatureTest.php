<?php

use Frolax\Payment\Models\Invoice;
use Frolax\Payment\Models\InvoiceItem;
use Frolax\Payment\Models\CreditNote;
use Frolax\Payment\Models\TaxRate;

// -------------------------------------------------------
// Invoice CRUD
// -------------------------------------------------------

test('invoice is created with items', function () {
    $invoice = Invoice::create([
        'number' => 'INV-001',
        'customer_email' => 'test@example.com',
        'customer_name' => 'Test User',
        'subtotal' => 100.00,
        'tax_amount' => 10.00,
        'total' => 110.00,
        'currency' => 'USD',
        'status' => 'unpaid',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Pro Plan - Monthly',
        'quantity' => 1,
        'unit_price' => 100.00,
        'total' => 100.00,
    ]);

    expect($invoice->exists)->toBeTrue();
    expect($invoice->items()->count())->toBe(1);
    expect($invoice->items->first()->description)->toBe('Pro Plan - Monthly');
});

test('invoice state transitions', function () {
    $invoice = Invoice::create([
        'number' => 'INV-002',
        'subtotal' => 50.00,
        'total' => 50.00,
        'currency' => 'USD',
        'status' => 'unpaid',
    ]);

    expect($invoice->status)->toBe('unpaid');

    $invoice->markPaid();
    expect($invoice->fresh()->status)->toBe('paid');

    $invoice2 = Invoice::create([
        'number' => 'INV-003',
        'subtotal' => 30.00,
        'total' => 30.00,
        'currency' => 'USD',
        'status' => 'unpaid',
    ]);

    $invoice2->markVoided();
    expect($invoice2->fresh()->status)->toBe('voided');
});

test('credit note is associated with invoice', function () {
    $invoice = Invoice::create([
        'number' => 'INV-004',
        'subtotal' => 200.00,
        'total' => 200.00,
        'currency' => 'USD',
        'status' => 'paid',
    ]);

    CreditNote::create([
        'number' => 'CN-001',
        'invoice_id' => $invoice->id,
        'amount' => 50.00,
        'reason' => 'Partial refund',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    expect($invoice->creditNotes()->count())->toBe(1);
    expect($invoice->creditNotes->first()->amount)->toBe('50.00');
});

// -------------------------------------------------------
// Tax Rate
// -------------------------------------------------------

test('tax rates are persisted and queried', function () {
    TaxRate::create([
        'name' => 'US California Sales Tax',
        'rate' => 7.25,
        'region' => 'US-CA',
        'is_inclusive' => false,
        'is_active' => true,
    ]);

    TaxRate::create([
        'name' => 'UK VAT',
        'rate' => 20.00,
        'region' => 'GB',
        'is_inclusive' => true,
        'is_active' => true,
    ]);

    $activeTaxes = TaxRate::active()->get();
    expect($activeTaxes)->toHaveCount(2);

    $usTax = TaxRate::where('region', 'US-CA')->first();
    expect((float) $usTax->rate)->toBe(7.25);
    expect($usTax->calculate(100.00))->toBe(7.25);

    $ukTax = TaxRate::where('region', 'GB')->first();
    expect($ukTax->is_inclusive)->toBeTrue();
});
