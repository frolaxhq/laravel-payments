<?php

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Credentials\CompositeCredentialsRepository;
use Frolax\Payment\Credentials\DatabaseCredentialsRepository;
use Frolax\Payment\Credentials\EnvCredentialsRepository;
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
    $payment = app(\Frolax\Payment\Payment::class);
    expect($payment)->toBeInstanceOf(\Frolax\Payment\Payment::class);

    $sub = app(\Frolax\Payment\SubscriptionManager::class);
    expect($sub)->toBeInstanceOf(\Frolax\Payment\SubscriptionManager::class);

    $refund = app(\Frolax\Payment\RefundManager::class);
    expect($refund)->toBeInstanceOf(\Frolax\Payment\RefundManager::class);
});
