<?php

use Frolax\Payment\Logging\PaymentLogger;
use Frolax\Payment\Models\PaymentLog;
use Frolax\Payment\PaymentConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('payment logger redacts configured keys and writes to DB', function () {
    config()->set('payments.logging.redacted_keys', ['secret', 'password']);
    config()->set('payments.persistence.enabled', true);
    config()->set('payments.persistence.logs', true);
    config()->set('payments.logging.db_logging', true);

    $config = new PaymentConfig;
    $logger = new PaymentLogger($config);

    // Test info
    $logger->info('payment.test', 'This is an info message', [
        'gateway' => ['name' => 'stripe'],
        'payment' => ['id' => 'pay_123'],
        'profile' => 'default',
        'tenant_id' => 'tenant_1',
        'attempt' => ['id' => 'att_123'],
        'sensitive' => [
            'my_secret' => 'do_not_log',
            'user_password' => '12345',
            'public_key' => 'ok',
        ],
    ]);

    $log = PaymentLog::first();

    expect($log)->not->toBeNull();
    expect($log->category)->toBe('payment.test');
    expect($log->message)->toBe('This is an info message');
    expect($log->level)->toBe('info');
    expect($log->gateway_name)->toBe('stripe');
    expect($log->payment_id)->toBe('pay_123');
    expect($log->profile)->toBe('default');
    expect($log->tenant_id)->toBe('tenant_1');
    expect($log->attempt_id)->toBe('att_123');

    // Check nested redaction
    $nested = $log->context_nested;
    expect($nested['sensitive']['my_secret'])->toBe('[REDACTED]');
    expect($nested['sensitive']['user_password'])->toBe('[REDACTED]');
    expect($nested['sensitive']['public_key'])->toBe('ok');

    // Check flat redaction
    $flat = $log->context_flat;
    expect($flat['sensitive.my_secret'])->toBe('[REDACTED]');
});

test('logger methods proxies and respects configured level', function () {
    config()->set('payments.logging.level', 'basic'); // basic allows warning, info, error
    config()->set('payments.persistence.enabled', true);
    config()->set('payments.persistence.logs', true);
    config()->set('payments.logging.db_logging', true);

    $config = new PaymentConfig;
    $logger = new PaymentLogger($config);

    $logger->debug('debug.cat', 'debug msg');
    $logger->warning('warning.cat', 'warning msg');
    $logger->error('error.cat', 'error msg');

    // debug shouldn't be logged since level is 'basic'
    expect(PaymentLog::forLevel('debug')->count())->toBe(0);
    expect(PaymentLog::forLevel('warning')->count())->toBe(1);
    expect(PaymentLog::forLevel('error')->count())->toBe(1);
});

test('logger gracefully catches DB exception and writes to Log channel', function () {
    config()->set('payments.persistence.enabled', true);
    config()->set('payments.persistence.logs', true);
    config()->set('payments.logging.db_logging', true);

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('info')->once();
    Log::shouldReceive('warning')->withArgs(function ($msg) {
        return str_contains($msg, 'Failed to write DB log');
    })->once();

    $config = new PaymentConfig;
    $logger = new PaymentLogger($config);

    // Force DB exception by pointing to fake table
    app('db')->statement('DROP TABLE IF EXISTS '.config('payments.tables.logs', 'payment_logs'));

    expect(fn () => $logger->info('cat', 'msg'))->not->toThrow(\Throwable::class);
});
