<?php

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsRecurring;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Exceptions\GatewayNotFoundException;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Services\SchemaValidator;
use Frolax\Payment\Services\WebhookRetryPolicy;
use Frolax\Payment\Services\WebhookRouter;

test('gateway registry registers and resolves string driver', function () {
    $registry = new GatewayRegistry;
    $driver = Mockery::mock(GatewayDriverContract::class);
    app()->instance('my_driver', $driver);

    $registry->register('my_gateway', 'my_driver', 'My Gateway', [SupportsRefund::class]);

    expect($registry->has('my_gateway'))->toBeTrue();
    expect($registry->keys())->toContain('my_gateway');
    expect($registry->resolve('my_gateway'))->toBe($driver);
    expect($registry->all()['my_gateway']['display_name'])->toBe('My Gateway');
});

test('gateway registry resolves callable driver', function () {
    $registry = new GatewayRegistry;
    $driver = Mockery::mock(GatewayDriverContract::class);

    $registry->register('cal', fn () => $driver);
    expect($registry->resolve('cal'))->toBe($driver);
});

test('gateway registry throws not found exception', function () {
    $registry = new GatewayRegistry;
    $registry->resolve('missing');
})->throws(GatewayNotFoundException::class);

test('gateway registry registers addon', function () {
    $registry = new GatewayRegistry;
    $addon = Mockery::mock(GatewayAddonContract::class);
    $addon->shouldReceive('gatewayKey')->andReturn('addon_gw');
    $addon->shouldReceive('driverClass')->andReturn('addon_driver');
    $addon->shouldReceive('displayName')->andReturn('Addon');
    $addon->shouldReceive('capabilities')->andReturn([SupportsRefund::class]);
    $addon->shouldReceive('credentialSchema')->andReturn(['key' => 'string']);

    $registry->registerAddon($addon);

    expect($registry->has('addon_gw'))->toBeTrue();
    expect($registry->addon('addon_gw'))->toBe($addon);
    expect($registry->credentialSchema('addon_gw'))->toBe(['key' => 'string']);
    expect($registry->capabilities('addon_gw'))->toBe([SupportsRefund::class]);
    expect($registry->hasCapability('addon_gw', SupportsRefund::class))->toBeTrue();
    expect($registry->hasCapability('addon_gw', SupportsRecurring::class))->toBeFalse();

    $supporting = $registry->supporting(SupportsRefund::class);
    expect($supporting)->toHaveKey('addon_gw');
});

test('gateway registry returns empty credential schema if no addon', function () {
    $registry = new GatewayRegistry;
    $registry->register('gw', 'drv');
    expect($registry->credentialSchema('gw'))->toBe([]);
});

test('gateway registry resolvedCapabilities inspects interfaces', function () {
    $registry = new GatewayRegistry;
    $driver = Mockery::mock(GatewayDriverContract::class.','.SupportsRefund::class.','.SupportsRecurring::class);
    $registry->register('test', fn () => $driver);

    $caps = $registry->resolvedCapabilities('test');
    expect($caps)->toContain(SupportsRefund::class);
    expect($caps)->toContain(SupportsRecurring::class);
});

test('webhook router resolves exact match', function () {
    config()->set('payments.webhooks.routes', ['payment.created' => 'CreatedClass']);
    $router = new WebhookRouter;

    expect($router->routes())->toHaveKey('payment.created', 'CreatedClass');
    expect($router->resolve('payment.created'))->toBe('CreatedClass');
});

test('webhook router resolves wildcard match', function () {
    $router = new WebhookRouter;
    $router->route('payment.*', 'WildcardClass');

    expect($router->resolve('payment.failed'))->toBe('WildcardClass');
    expect($router->resolve('subscription.created'))->toBeNull();
});

test('webhook retry policy works with exponential strategy', function () {
    config()->set('payments.webhooks.retry_attempts', 3);
    config()->set('payments.webhooks.retry_backoff', 'exponential');
    config()->set('payments.webhooks.retry_delay_seconds', 60);

    $policy = new WebhookRetryPolicy;
    expect($policy->maxAttempts())->toBe(3);
    expect($policy->shouldRetry(1))->toBeTrue();
    expect($policy->shouldRetry(3))->toBeFalse();

    // Exponential: 60 * 2^(attempt-1)
    expect($policy->getDelay(1))->toBe(60); // 60 * 2^0
    expect($policy->getDelay(2))->toBe(120); // 60 * 2^1
    expect($policy->getDelay(3))->toBe(240); // 60 * 2^2
});

test('webhook retry policy works with linear strategy', function () {
    config()->set('payments.webhooks.retry_backoff', 'linear');
    $policy = new WebhookRetryPolicy;

    // Linear: 60 * attempt
    expect($policy->getDelay(1))->toBe(60);
    expect($policy->getDelay(2))->toBe(120);
    expect($policy->getDelay(3))->toBe(180);
});

test('webhook retry policy works with fixed strategy', function () {
    config()->set('payments.webhooks.retry_backoff', 'fixed');
    $policy = new WebhookRetryPolicy;

    expect($policy->getDelay(1))->toBe(60);
    expect($policy->getDelay(2))->toBe(60);
});

test('schema validator validates core rules successfully', function () {
    $validator = new SchemaValidator;
    $data = [
        'order' => ['id' => '1'],
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ];

    expect($validator->passes($data))->toBeTrue();
});

test('schema validator validates core rules failure', function () {
    $validator = new SchemaValidator;

    // Missing money and order
    $data = [];
    $errors = $validator->validate($data);
    expect(count($errors))->toBeGreaterThan(0);
    expect($errors[0]['rule'])->toBe('required');

    // Negative amount and bad currency
    $data = [
        'order' => ['id' => '1'],
        'money' => ['amount' => -5, 'currency' => 'US'],
    ];
    $errors = $validator->validate($data);

    $rules = array_column($errors, 'rule');
    expect($rules)->toContain('positive');
    expect($rules)->toContain('currency_format');
    expect($validator->passes($data))->toBeFalse();
});

test('schema validator validates gateway specific rules', function () {
    $validator = new SchemaValidator;
    $validator->forGateway('stripe', ['customer.email' => 'required']);

    $data = [
        'order' => ['id' => '1'],
        'money' => ['amount' => 10, 'currency' => 'USD'],
    ];

    // Passes without gateway specific requirements
    expect($validator->passes($data))->toBeTrue();

    // Fails with gateway requirements
    expect($validator->passes($data, 'stripe'))->toBeFalse();

    $errors = $validator->validate($data, 'stripe');
    expect($errors[0]['field'])->toBe('customer.email');

    // Passes with gateway requirements met
    $data['customer']['email'] = 'test@example.com';
    expect($validator->passes($data, 'stripe'))->toBeTrue();
});
