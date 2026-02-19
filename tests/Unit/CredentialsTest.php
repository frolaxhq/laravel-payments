<?php

use Frolax\Payment\Credentials\EnvCredentialsRepository;

test('env repository resolves credentials from config', function () {
    config()->set('payments.gateways.test_gw.test', [
        'key' => 'test_key',
        'secret' => 'test_secret',
    ]);

    $repo = new EnvCredentialsRepository;
    $creds = $repo->get('test_gw', 'test');

    expect($creds)->not->toBeNull();
    expect($creds->gateway)->toBe('test_gw');
    expect($creds->profile)->toBe('test');
    expect($creds->get('key'))->toBe('test_key');
    expect($creds->get('secret'))->toBe('test_secret');
});

test('env repository returns null for missing gateway', function () {
    $repo = new EnvCredentialsRepository;
    $creds = $repo->get('nonexistent', 'test');

    expect($creds)->toBeNull();
});

test('env repository has() returns true for existing credentials', function () {
    config()->set('payments.gateways.check_gw.live', ['key' => 'k']);

    $repo = new EnvCredentialsRepository;

    expect($repo->has('check_gw', 'live'))->toBeTrue();
    expect($repo->has('check_gw', 'test'))->toBeFalse();
});
