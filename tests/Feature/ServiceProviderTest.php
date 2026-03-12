<?php

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Credentials\CompositeCredentialsRepository;
use Frolax\Payment\Credentials\DatabaseCredentialsRepository;
use Frolax\Payment\Credentials\EnvCredentialsRepository;
use Frolax\Payment\Payment;
use Frolax\Payment\RefundManager;
use Frolax\Payment\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('service provider registers database credentials repository', function () {
    config()->set('payments.credential_storage', 'database');

    // Forget existing instances so the provider resolves fresh
    app()->forgetInstance(CredentialsRepositoryContract::class);

    $repo = app(CredentialsRepositoryContract::class);
    expect($repo)->toBeInstanceOf(DatabaseCredentialsRepository::class);
});

test('service provider registers composite credentials repository', function () {
    config()->set('payments.credential_storage', 'composite');
    app()->forgetInstance(CredentialsRepositoryContract::class);

    $repo = app(CredentialsRepositoryContract::class);
    expect($repo)->toBeInstanceOf(CompositeCredentialsRepository::class);
});

test('service provider registers env credentials repository as default', function () {
    config()->set('payments.credential_storage', 'unknown');
    app()->forgetInstance(CredentialsRepositoryContract::class);

    $repo = app(CredentialsRepositoryContract::class);
    expect($repo)->toBeInstanceOf(EnvCredentialsRepository::class);
});

test('service provider resolves managers from app container correctly', function () {
    $payment = app(Payment::class);
    expect($payment)->toBeInstanceOf(Payment::class);

    $sub = app(SubscriptionManager::class);
    expect($sub)->toBeInstanceOf(SubscriptionManager::class);

    $refund = app(RefundManager::class);
    expect($refund)->toBeInstanceOf(RefundManager::class);
});
