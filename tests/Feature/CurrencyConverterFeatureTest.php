<?php

use Frolax\Payment\Models\ExchangeRate;
use Frolax\Payment\Services\CurrencyConverter;

// -------------------------------------------------------
// ExchangeRate persistence
// -------------------------------------------------------

test('exchange rate is stored and retrieved', function () {
    ExchangeRate::create([
        'from_currency' => 'USD',
        'to_currency' => 'BDT',
        'rate' => 109.50,
        'source' => 'central_bank',
        'fetched_at' => now(),
    ]);

    $rate = ExchangeRate::latest('USD', 'BDT');

    expect($rate)->not->toBeNull();
    expect($rate->from_currency)->toBe('USD');
    expect($rate->to_currency)->toBe('BDT');
    expect((float) $rate->rate)->toBe(109.50);
});

test('exchange rate convert method', function () {
    ExchangeRate::create([
        'from_currency' => 'USD',
        'to_currency' => 'EUR',
        'rate' => 0.925,
        'source' => 'ecb',
        'fetched_at' => now(),
    ]);

    $rate = ExchangeRate::latest('USD', 'EUR');
    expect($rate->convert(100))->toBe(92.50);
});

// -------------------------------------------------------
// CurrencyConverter service
// -------------------------------------------------------

test('currency converter converts using stored rate', function () {
    ExchangeRate::create([
        'from_currency' => 'USD',
        'to_currency' => 'GBP',
        'rate' => 0.79,
        'source' => 'manual',
        'fetched_at' => now(),
    ]);

    $converter = app(CurrencyConverter::class);
    $result = $converter->convert(100, 'USD', 'GBP');

    expect($result['original_amount'])->toBe(100.0);
    expect($result['converted_amount'])->toBe(79.0);
    expect((float) $result['rate'])->toBe(0.79);
    expect($result['from'])->toBe('USD');
    expect($result['to'])->toBe('GBP');
});

test('currency converter handles same currency', function () {
    $converter = app(CurrencyConverter::class);
    $result = $converter->convert(50.00, 'USD', 'USD');

    expect($result['converted_amount'])->toBe(50.00);
    expect($result['rate'])->toBe(1.0);
    expect($result['source'])->toBe('identity');
});

test('currency converter resolves reverse rate', function () {
    ExchangeRate::create([
        'from_currency' => 'EUR',
        'to_currency' => 'USD',
        'rate' => 1.08,
        'source' => 'ecb',
        'fetched_at' => now(),
    ]);

    $converter = app(CurrencyConverter::class);
    $result = $converter->convert(100, 'USD', 'EUR');

    // 1/1.08 ≈ 0.9259, so 100 * 0.9259 ≈ 92.59
    expect($result['converted_amount'])->toBe(92.59);
    expect($result['source'])->toContain('inverted');
});

test('currency converter throws for missing rate', function () {
    $converter = app(CurrencyConverter::class);
    $converter->convert(100, 'XYZ', 'ABC');
})->throws(RuntimeException::class, 'No exchange rate found');

test('currency converter setRate stores rate', function () {
    $converter = app(CurrencyConverter::class);
    $rate = $converter->setRate('USD', 'JPY', 149.50, 'api');

    expect($rate)->toBeInstanceOf(ExchangeRate::class);
    expect($rate->exists)->toBeTrue();
    expect(ExchangeRate::latest('USD', 'JPY'))->not->toBeNull();
});
