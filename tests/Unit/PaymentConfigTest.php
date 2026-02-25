<?php

use Frolax\Payment\PaymentConfig;

test('payment config persistence getters', function () {
    config()->set('payments.persistence.enabled', true);
    config()->set('payments.persistence.payments', true);
    config()->set('payments.persistence.attempts', false);
    config()->set('payments.persistence.refunds', true);
    config()->set('payments.persistence.webhooks', false);
    config()->set('payments.persistence.logs', true);

    $config = new PaymentConfig;

    expect($config->shouldPersistPayments())->toBeTrue();
    expect($config->shouldPersistAttempts())->toBeFalse();
    expect($config->shouldPersistRefunds())->toBeTrue();
    expect($config->shouldPersistWebhooks())->toBeFalse();
    expect($config->shouldPersistLogs())->toBeTrue();

    config()->set('payments.persistence.enabled', false);
    $config2 = new PaymentConfig;
    expect($config2->shouldPersistPayments())->toBeFalse();
    expect($config2->shouldPersistRefunds())->toBeFalse();
});
