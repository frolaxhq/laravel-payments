<?php

use Frolax\Payment\Http\Controllers\CancelController;
use Frolax\Payment\Http\Controllers\ReturnController;
use Frolax\Payment\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

$prefix = config('payments.routes.prefix', 'payments');
$middleware = config('payments.routes.middleware', ['web']);
$webhookMiddleware = config('payments.routes.webhook_middleware', []);

// Webhook endpoint (no CSRF, no web middleware by default)
Route::post("{$prefix}/webhook/{gateway}", WebhookController::class)
    ->middleware($webhookMiddleware)
    ->name('payments.webhook');

// Return endpoint (after hosted checkout)
Route::get("{$prefix}/return/{gateway}", ReturnController::class)
    ->middleware($middleware)
    ->name('payments.return');

// Cancel endpoint
Route::get("{$prefix}/cancel/{gateway}", CancelController::class)
    ->middleware($middleware)
    ->name('payments.cancel');
