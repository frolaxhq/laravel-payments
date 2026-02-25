<?php

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Credentials\CompositeCredentialsRepository;
use Frolax\Payment\Data\Credentials;

test('composite repository returns default instance', function () {
    $repo = CompositeCredentialsRepository::default();

    // We can't introspect protected properties easily without reflection,
    // but we can ensure it is an instance of CompositeCredentialsRepository
    expect($repo)->toBeInstanceOf(CompositeCredentialsRepository::class);
});

test('composite repository get returns first match', function () {
    $repo1 = Mockery::mock(CredentialsRepositoryContract::class);
    $repo2 = Mockery::mock(CredentialsRepositoryContract::class);

    $dto = new Credentials('test_gateway', 'test', ['key' => 'val']);

    $repo1->shouldReceive('get')->with('test_gateway', 'test', [])->andReturn(null);
    $repo2->shouldReceive('get')->with('test_gateway', 'test', [])->andReturn($dto);

    $composite = new CompositeCredentialsRepository([$repo1, $repo2]);

    expect($composite->get('test_gateway', 'test'))->toBe($dto);
});

test('composite repository get returns null if none match', function () {
    $repo1 = Mockery::mock(CredentialsRepositoryContract::class);

    $repo1->shouldReceive('get')->with('test_gateway', 'test', [])->andReturn(null);

    $composite = new CompositeCredentialsRepository([$repo1]);

    expect($composite->get('test_gateway', 'test'))->toBeNull();
});

test('composite repository has returns true if any match', function () {
    $repo1 = Mockery::mock(CredentialsRepositoryContract::class);
    $repo2 = Mockery::mock(CredentialsRepositoryContract::class);

    $repo1->shouldReceive('has')->with('test_gateway', 'test', [])->andReturn(false);
    $repo2->shouldReceive('has')->with('test_gateway', 'test', [])->andReturn(true);

    $composite = new CompositeCredentialsRepository([$repo1, $repo2]);

    expect($composite->has('test_gateway', 'test'))->toBeTrue();
});

test('composite repository has returns false if none match', function () {
    $repo1 = Mockery::mock(CredentialsRepositoryContract::class);

    $repo1->shouldReceive('has')->with('test_gateway', 'test', [])->andReturn(false);

    $composite = new CompositeCredentialsRepository([$repo1]);

    expect($composite->has('test_gateway', 'test'))->toBeFalse();
});
