<?php

use Frolax\Payment\Credentials\DatabaseCredentialsRepository;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

test('database repository returns null if no active credentials', function () {
    $repo = new DatabaseCredentialsRepository;
    expect($repo->get('test_gateway', 'test'))->toBeNull();
});

test('database repository retrieves active credential and decrypts', function () {
    PaymentGatewayCredential::create([
        'gateway_name' => 'test_gateway',
        'profile' => 'test',
        'is_active' => true,
        'credentials' => ['key' => 'secret_val'],
        'label' => 'Primary Credentials',
    ]);

    $repo = new DatabaseCredentialsRepository;
    $record = PaymentGatewayCredential::first();
    dump($record->getAttributes(), $record->credentials);

    $dto = $repo->get('test_gateway', 'test');

    expect($dto)->toBeInstanceOf(Credentials::class)
        ->and($dto->credentials)->toBe(['key' => 'secret_val'])
        ->and($dto->label)->toBe('Primary Credentials');
});





test('database repository has returns true if credential exists', function () {
    PaymentGatewayCredential::create([
        'gateway_name' => 'has_gateway',
        'profile' => 'test',
        'is_active' => true,
        'credentials' => ['key' => 'val'],
    ]);

    $repo = new DatabaseCredentialsRepository;
    expect($repo->has('has_gateway', 'test'))->toBeTrue()
        ->and($repo->has('has_gateway', 'live'))->toBeFalse();
});
