<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up potentially generated files in the dummy app
    $this->tempAddonPath = base_path('packages/laravel-payments-test-gateway');
    $this->tempInlinePath = base_path('app/Payment/Gateways/TestInline');
    $this->tempInlineTestPath = base_path('tests/Payment/Gateways/TestInline');

    if (File::exists($this->tempAddonPath)) {
        File::deleteDirectory($this->tempAddonPath);
    }
    if (File::exists($this->tempInlinePath)) {
        File::deleteDirectory($this->tempInlinePath);
    }
    if (File::exists($this->tempInlineTestPath)) {
        File::deleteDirectory($this->tempInlineTestPath);
    }
});

afterEach(function () {
    if (File::exists($this->tempAddonPath)) {
        File::deleteDirectory($this->tempAddonPath);
    }
    if (File::exists($this->tempInlinePath)) {
        File::deleteDirectory($this->tempInlinePath);
    }
    if (File::exists($this->tempInlineTestPath)) {
        File::deleteDirectory($this->tempInlineTestPath);
    }
});

test('make-gateway command generates an inline gateway', function () {
    $this->artisan('payments:make-gateway', [
        'name' => 'Test Inline',
        '--key' => 'test_inline',
        '--display' => 'Test Inline DB',
        '--capabilities' => 'redirect,webhook,refund,status',
    ])->assertExitCode(0)
        ->expectsOutputToContain('skeleton generated successfully!');

    expect(File::exists($this->tempInlinePath.'/TestInlineDriver.php'))->toBeTrue()
        ->and(File::exists($this->tempInlinePath.'/config_snippet.php'))->toBeTrue()
        ->and(File::exists($this->tempInlinePath.'/README.md'))->toBeTrue()
        ->and(File::exists($this->tempInlineTestPath.'/TestInlineDriverTest.php'))->toBeTrue();
});

test('make-gateway command generates an addon gateway', function () {
    $this->artisan('payments:make-gateway', [
        'name' => 'Test Gateway',
        '--addon' => true,
        '--key' => 'test_gateway',
        '--capabilities' => 'redirect,webhook,refund,status',
    ])->assertExitCode(0)
        ->expectsOutputToContain('Addon package');

    expect(File::exists($this->tempAddonPath.'/composer.json'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/src/TestGatewayDriver.php'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/src/TestGatewayGatewayAddon.php'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/src/TestGatewayServiceProvider.php'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/config/payment-test-gateway.php'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/tests/TestGatewayDriverTest.php'))->toBeTrue()
        ->and(File::exists($this->tempAddonPath.'/README.md'))->toBeTrue();
});
