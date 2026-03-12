<?php

use Frolax\Payment\Contracts\GatewayAddonContract;
use Frolax\Payment\Contracts\GatewayDriverContract;
use Frolax\Payment\Contracts\SupportsRefund;
use Frolax\Payment\Data\Credentials;
use Frolax\Payment\Data\GatewayResult;
use Frolax\Payment\Data\Payload;
use Frolax\Payment\Enums\PaymentStatus;
use Frolax\Payment\GatewayRegistry;
use Frolax\Payment\Testing\FakeDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->registry = app(GatewayRegistry::class);
    $this->registry->register('fake_gateway', FakeDriver::class, 'Fake Gateway');
});

test('list gateways command shows empty when none registered', function () {
    app()->instance(GatewayRegistry::class, new GatewayRegistry); // empty registry
    $this->artisan('payments:gateways')
        ->expectsOutputToContain('No gateways registered.')
        ->assertExitCode(0);
});

test('list gateways command lists registered gateways with capabilities and currencies', function () {
    $addon = new class implements GatewayAddonContract
    {
        public function gatewayKey(): string
        {
            return 'fake_gateway';
        }

        public function displayName(): string
        {
            return 'Fake Gateway';
        }

        public function driverClass(): string|callable
        {
            return FakeDriver::class;
        }

        public function capabilities(): array
        {
            return [SupportsRefund::class, 'CustomCapability'];
        }

        public function credentialSchema(): array
        {
            return [];
        }

        public function defaultConfig(): array
        {
            return [];
        }
    };

    $mockRegistry = Mockery::mock(GatewayRegistry::class)->makePartial();
    $mockRegistry->shouldReceive('all')->andReturn([
        'fake_gateway' => [
            'driver' => FakeDriver::class,
            'display_name' => 'Fake Gateway',
            'capabilities' => [SupportsRefund::class, 'CustomCapability'],
            'addon' => $addon,
            'supported_currencies' => ['USD', 'EUR'],
        ],
        'default_gateway' => [
            'driver' => FakeDriver::class,
            'display_name' => 'Default Gateway',
            'capabilities' => [],
            'addon' => null,
            // no supported_currencies
        ],
    ]);

    app()->instance(GatewayRegistry::class, $mockRegistry);

    Artisan::call('payments:gateways');
    $output = Artisan::output();

    expect($output)->toContain('fake_gateway')
        ->toContain('Fake Gateway')
        ->toContain('Refund, CustomCapability')
        ->toContain('USD, EUR')
        ->toContain('addon');
});

test('validate credentials command succeeds on valid credentials', function () {
    // Register driver with requirement
    $driver = new class implements GatewayDriverContract
    {
        public function name(): string
        {
            return 'req_gateway';
        }

        public function setCredentials(Credentials $credentials): GatewayDriverContract
        {
            return $this;
        }

        public function create(Payload $payload, Credentials $credentials): GatewayResult
        {
            return new GatewayResult(PaymentStatus::Pending);
        }

        public function verify(Request $request, Credentials $credentials): GatewayResult
        {
            return new GatewayResult(PaymentStatus::Pending);
        }
    };

    $addon = current(array_filter($this->registry->all(), fn ($g) => $g['driver'] === FakeDriver::class))['addon'] ?? null;

    // Create a mock addon to have credentialSchema
    $mockAddon = new class implements GatewayAddonContract
    {
        public function gatewayKey(): string
        {
            return 'req_gateway';
        }

        public function displayName(): string
        {
            return 'Req Gateway';
        }

        public function driverClass(): string|callable
        {
            return 'ReqDriver';
        }

        public function capabilities(): array
        {
            return [];
        }

        public function credentialSchema(): array
        {
            return ['key' => 'required'];
        }

        public function defaultConfig(): array
        {
            return [];
        }
    };

    $this->registry->registerAddon($mockAddon);

    // Set config so EnvRepository gets it
    config()->set('payments.gateways.req_gateway.test.key', 'val');

    $this->artisan('payments:credentials:validate', ['--profile' => 'test'])
        ->expectsOutputToContain('All required credentials present')
        ->expectsOutputToContain('All gateway credentials are valid.')
        ->assertExitCode(0);
});

test('validate credentials command fails when missing credentials', function () {
    $mockAddon = new class implements GatewayAddonContract
    {
        public function gatewayKey(): string
        {
            return 'missing_gateway';
        }

        public function displayName(): string
        {
            return 'Missing Gateway';
        }

        public function driverClass(): string|callable
        {
            return 'MissingDriver';
        }

        public function capabilities(): array
        {
            return [];
        }

        public function credentialSchema(): array
        {
            return ['key' => 'required'];
        }

        public function defaultConfig(): array
        {
            return [];
        }
    };

    $this->registry->registerAddon($mockAddon);

    // No config set
    $this->artisan('payments:credentials:validate', ['--gateway' => 'missing_gateway'])
        ->expectsOutputToContain('Some gateways have missing credentials.')
        ->assertExitCode(1);
});

test('validate credentials command fails on missing required keys', function () {
    $mockAddon = new class implements GatewayAddonContract
    {
        public function gatewayKey(): string
        {
            return 'part_gateway';
        }

        public function displayName(): string
        {
            return 'Part Gateway';
        }

        public function driverClass(): string|callable
        {
            return 'PartDriver';
        }

        public function capabilities(): array
        {
            return [];
        }

        public function credentialSchema(): array
        {
            return ['key' => 'required', 'secret' => 'required'];
        }

        public function defaultConfig(): array
        {
            return [];
        }
    };

    $this->registry->registerAddon($mockAddon);

    // Only key is set
    config()->set('payments.gateways.part_gateway.test.key', 'val');
    config()->set('payments.gateways.part_gateway.test.secret', ''); // empty

    $this->artisan('payments:credentials:validate', ['--gateway' => 'part_gateway'])
        ->expectsOutputToContain('Missing required keys: secret')
        ->assertExitCode(1);
});
