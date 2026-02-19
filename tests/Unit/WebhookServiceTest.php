<?php

use Frolax\Payment\Services\WebhookRetryPolicy;
use Frolax\Payment\Services\WebhookRouter;

// -------------------------------------------------------
// WebhookRouter
// -------------------------------------------------------

test('WebhookRouter resolves exact match', function () {
    $router = new WebhookRouter;
    $router->route('payment.completed', 'App\Handlers\PaymentCompleted');

    expect($router->resolve('payment.completed'))->toBe('App\Handlers\PaymentCompleted');
});

test('WebhookRouter resolves wildcard match', function () {
    $router = new WebhookRouter;
    $router->route('payment.*', 'App\Handlers\PaymentHandler');

    expect($router->resolve('payment.completed'))->toBe('App\Handlers\PaymentHandler');
    expect($router->resolve('payment.failed'))->toBe('App\Handlers\PaymentHandler');
});

test('WebhookRouter returns null for unmatched event', function () {
    $router = new WebhookRouter;
    $router->route('payment.*', 'App\Handlers\PaymentHandler');

    expect($router->resolve('subscription.cancelled'))->toBeNull();
});

test('WebhookRouter prefers exact match over wildcard', function () {
    $router = new WebhookRouter;
    $router->route('payment.completed', 'App\Handlers\Exact');
    $router->route('payment.*', 'App\Handlers\Wildcard');

    expect($router->resolve('payment.completed'))->toBe('App\Handlers\Exact');
});

test('WebhookRouter lists all routes', function () {
    $router = new WebhookRouter;
    $router->route('payment.completed', 'Handler1');
    $router->route('subscription.*', 'Handler2');

    expect($router->routes())->toHaveCount(2);
});

// -------------------------------------------------------
// WebhookRetryPolicy
// -------------------------------------------------------

test('WebhookRetryPolicy allows retry within max attempts', function () {
    config()->set('payments.webhooks.retry_attempts', 3);
    $policy = new WebhookRetryPolicy;

    expect($policy->shouldRetry(1))->toBeTrue();
    expect($policy->shouldRetry(2))->toBeTrue();
    expect($policy->shouldRetry(3))->toBeFalse();
});

test('WebhookRetryPolicy calculates exponential backoff', function () {
    config()->set('payments.webhooks.retry_backoff', 'exponential');
    config()->set('payments.webhooks.retry_delay_seconds', 60);
    $policy = new WebhookRetryPolicy;

    expect($policy->getDelay(1))->toBe(60);   // 60 * 2^0
    expect($policy->getDelay(2))->toBe(120);  // 60 * 2^1
    expect($policy->getDelay(3))->toBe(240);  // 60 * 2^2
});

test('WebhookRetryPolicy calculates linear backoff', function () {
    config()->set('payments.webhooks.retry_backoff', 'linear');
    config()->set('payments.webhooks.retry_delay_seconds', 30);
    $policy = new WebhookRetryPolicy;

    expect($policy->getDelay(1))->toBe(30);
    expect($policy->getDelay(2))->toBe(60);
    expect($policy->getDelay(3))->toBe(90);
});

test('WebhookRetryPolicy calculates fixed delay', function () {
    config()->set('payments.webhooks.retry_backoff', 'fixed');
    config()->set('payments.webhooks.retry_delay_seconds', 45);
    $policy = new WebhookRetryPolicy;

    expect($policy->getDelay(1))->toBe(45);
    expect($policy->getDelay(2))->toBe(45);
    expect($policy->getDelay(3))->toBe(45);
});
