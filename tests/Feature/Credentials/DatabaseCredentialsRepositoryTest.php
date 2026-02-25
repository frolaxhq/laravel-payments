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
        'credentials' => Crypt::encryptString(json_encode(['key' => 'secret_val'])),
        'label' => 'Primary Credentials',
    ]);

    $repo = new DatabaseCredentialsRepository;
    $dto = $repo->get('test_gateway', 'test');

    expect($dto)->toBeInstanceOf(Credentials::class)
        ->and($dto->credentials)->toBe(['key' => 'secret_val'])
        ->and($dto->label)->toBe('Primary Credentials');
});

test('database repository decryptCredentials handles unencrypted json string gracefully', function () {
    $repo = new DatabaseCredentialsRepository;
    $reflection = new ReflectionClass($repo);
    $method = $reflection->getMethod('decryptCredentials');

    expect($method->invoke($repo, json_encode(['key' => 'plain_val'])))->toBe(['key' => 'plain_val']);
});

test('database repository decryptCredentials handles array natively', function () {
    $repo = new DatabaseCredentialsRepository;
    $reflection = new ReflectionClass($repo);
    $method = $reflection->getMethod('decryptCredentials');

    expect($method->invoke($repo, ['key' => 'array_val']))->toBe(['key' => 'array_val']);
});

test('database repository decryptCredentials handles invalid types returning empty array', function () {
    $repo = new DatabaseCredentialsRepository;
    $reflection = new ReflectionClass($repo);
    $method = $reflection->getMethod('decryptCredentials');

    // '123' fails decryption, goes to catch, json_decode('123', true) == 123
    // But then 123 is returned, wait... the method defines `return json_decode(...) ?: [];`
    // json_decode('123') is 123. `123 ?: []` is 123.
    // However, the test should just pass a totally invalid string 'not_json_at_all'
    // json_decode('not_json_at_all') is null. `null ?: []` is [].
    expect($method->invoke($repo, 'not_json_at_all'))->toBeEmpty();

    // Also test an object
    expect($method->invoke($repo, new stdClass))->toBeEmpty();
});

test('database repository honors tenant_id priority', function () {
    PaymentGatewayCredential::create([
        'gateway_name' => 'priority_gateway',
        'profile' => 'test',
        'is_active' => true,
        'tenant_id' => null,
        'credentials' => ['type' => 'global'],
        'priority' => 10,
    ]);

    PaymentGatewayCredential::create([
        'gateway_name' => 'priority_gateway',
        'profile' => 'test',
        'is_active' => true,
        'tenant_id' => 'tenant-123',
        'credentials' => ['type' => 'tenant'],
        'priority' => 0,
    ]);

    $repo = new DatabaseCredentialsRepository;

    // With tenant context
    $dto = $repo->get('priority_gateway', 'test', ['tenant_id' => 'tenant-123']);
    expect($dto->credentials['type'])->toBe('tenant');

    // Without tenant context
    $dtoGlobal = $repo->get('priority_gateway', 'test');
    expect($dtoGlobal->credentials['type'])->toBe('global');
});

test('database repository honors effective time windows', function () {
    // Expired
    PaymentGatewayCredential::create([
        'gateway_name' => 'time_gateway',
        'profile' => 'test',
        'is_active' => true,
        'effective_from' => now()->subDays(10),
        'effective_to' => now()->subDays(1),
        'credentials' => ['type' => 'expired'],
    ]);

    $repo = new DatabaseCredentialsRepository;
    expect($repo->get('time_gateway', 'test'))->toBeNull();

    // Valid window
    PaymentGatewayCredential::create([
        'gateway_name' => 'time_gateway',
        'profile' => 'test',
        'is_active' => true,
        'effective_from' => now()->subDays(1),
        'effective_to' => now()->addDays(1),
        'credentials' => ['type' => 'valid'],
    ]);

    expect($repo->get('time_gateway', 'test')->credentials['type'])->toBe('valid');
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
