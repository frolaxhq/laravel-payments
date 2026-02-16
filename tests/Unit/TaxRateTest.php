<?php

use Frolax\Payment\Models\TaxRate;

test('TaxRate calculates exclusive tax', function () {
    $rate = new TaxRate([
        'name' => 'VAT',
        'rate' => 20,
        'is_inclusive' => false,
        'is_active' => true,
    ]);

    expect($rate->calculate(100))->toBe(20.00);
    expect($rate->calculate(49.99))->toBe(10.00);
});

test('TaxRate calculates inclusive tax', function () {
    $rate = new TaxRate([
        'name' => 'GST',
        'rate' => 10,
        'is_inclusive' => true,
        'is_active' => true,
    ]);

    // 100 includes 10% tax, so tax portion = 100 - (100 / 1.10) ≈ 9.09
    expect($rate->calculate(100))->toBe(9.09);
});

test('TaxRate calculates zero for zero rate', function () {
    $rate = new TaxRate([
        'name' => 'Zero',
        'rate' => 0,
        'is_inclusive' => false,
    ]);

    expect($rate->calculate(100))->toBe(0.00);
});
