<?php

namespace Frolax\Payment\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeGatewayCommand extends Command
{
    protected $signature = 'payments:make-gateway {name : The gateway name (e.g. Stripe, Bkash)}
                            {--addon : Generate as a separate installable addon package}
                            {--key= : Gateway key (lowercase, e.g. bkash)}
                            {--display= : Display name (e.g. "bKash")}
                            {--capabilities=redirect,webhook : Comma-separated capabilities}
                            {--profile-support=test,live : Comma-separated profiles}
                            {--credentials=key:required,secret:required : Credential schema}
                            {--routes=universal : Route mode (universal|custom)}
                            {--http-client=guzzle : HTTP client to use}
                            {--namespace=Frolax\\Payments\\Gateways : Base namespace}';

    protected $description = 'Generate a payment gateway driver skeleton with tests and docs';

    public function handle(): int
    {
        $name = $this->argument('name');
        $key = $this->option('key') ?: Str::snake($name);
        $displayName = $this->option('display') ?: $name;
        $capabilities = explode(',', $this->option('capabilities') ?: 'redirect,webhook');
        $profiles = explode(',', $this->option('profile-support') ?: 'test,live');
        $credentials = $this->parseCredentials($this->option('credentials') ?: 'key:required,secret:required');
        $routeMode = $this->option('routes') ?: 'universal';
        $httpClient = $this->option('http-client') ?: 'guzzle';
        $namespace = $this->option('namespace') ?: 'Frolax\\Payments\\Gateways';
        $isAddon = $this->option('addon');

        $className = Str::studly($name);

        if ($isAddon) {
            return $this->generateAddon($name, $key, $displayName, $className, $capabilities, $profiles, $credentials, $namespace);
        }

        return $this->generateInline($name, $key, $displayName, $className, $capabilities, $profiles, $credentials, $namespace);
    }

    protected function generateInline(
        string $name,
        string $key,
        string $displayName,
        string $className,
        array $capabilities,
        array $profiles,
        array $credentials,
        string $namespace,
    ): int {
        $basePath = base_path("app/Payment/Gateways/{$className}");

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Generate driver class
        $driverContent = $this->generateDriverClass($className, $key, $displayName, $capabilities, $credentials, $namespace, false);
        file_put_contents("{$basePath}/{$className}Driver.php", $driverContent);
        $this->info("Created: {$basePath}/{$className}Driver.php");

        // Generate test file
        $testDir = base_path("tests/Payment/Gateways/{$className}");
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        $testContent = $this->generateTestFile($className, $key, $namespace, false);
        file_put_contents("{$testDir}/{$className}DriverTest.php", $testContent);
        $this->info("Created: {$testDir}/{$className}DriverTest.php");

        // Generate config snippet
        $configContent = $this->generateConfigSnippet($key, $profiles, $credentials);
        file_put_contents("{$basePath}/config_snippet.php", $configContent);
        $this->info("Created: {$basePath}/config_snippet.php");

        // Generate docs
        $docsContent = $this->generateDocs($name, $key, $displayName, $capabilities, $credentials, $profiles);
        file_put_contents("{$basePath}/README.md", $docsContent);
        $this->info("Created: {$basePath}/README.md");

        $this->newLine();
        $this->info("Gateway [{$displayName}] skeleton generated successfully!");
        $this->line("Register it in your service provider:");
        $this->line("  \$registry->register('{$key}', \\App\\Payment\\Gateways\\{$className}\\{$className}Driver::class);");

        return self::SUCCESS;
    }

    protected function generateAddon(
        string $name,
        string $key,
        string $displayName,
        string $className,
        array $capabilities,
        array $profiles,
        array $credentials,
        string $namespace,
    ): int {
        $vendorName = Str::kebab($name);
        $basePath = base_path("packages/frolax/payment-{$vendorName}");

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $dirs = ['src', 'config', 'tests', 'docs'];
        foreach ($dirs as $dir) {
            if (!is_dir("{$basePath}/{$dir}")) {
                mkdir("{$basePath}/{$dir}", 0755, true);
            }
        }

        $addonNamespace = "Frolax\\Payment{$className}";

        // composer.json
        $composerContent = $this->generateAddonComposer($vendorName, $name, $className, $addonNamespace);
        file_put_contents("{$basePath}/composer.json", $composerContent);
        $this->info("Created: {$basePath}/composer.json");

        // Driver class
        $driverContent = $this->generateDriverClass($className, $key, $displayName, $capabilities, $credentials, $addonNamespace, true);
        file_put_contents("{$basePath}/src/{$className}Driver.php", $driverContent);
        $this->info("Created: {$basePath}/src/{$className}Driver.php");

        // Gateway Addon Provider
        $addonProviderContent = $this->generateAddonProvider($className, $key, $displayName, $capabilities, $credentials, $addonNamespace);
        file_put_contents("{$basePath}/src/{$className}GatewayAddon.php", $addonProviderContent);
        $this->info("Created: {$basePath}/src/{$className}GatewayAddon.php");

        // Service Provider
        $serviceProviderContent = $this->generateAddonServiceProvider($className, $addonNamespace);
        file_put_contents("{$basePath}/src/{$className}ServiceProvider.php", $serviceProviderContent);
        $this->info("Created: {$basePath}/src/{$className}ServiceProvider.php");

        // Config
        $configFileContent = $this->generateAddonConfig($key, $profiles, $credentials);
        file_put_contents("{$basePath}/config/payment-{$vendorName}.php", $configFileContent);
        $this->info("Created: {$basePath}/config/payment-{$vendorName}.php");

        // Test
        $testContent = $this->generateTestFile($className, $key, $addonNamespace, true);
        file_put_contents("{$basePath}/tests/{$className}DriverTest.php", $testContent);
        $this->info("Created: {$basePath}/tests/{$className}DriverTest.php");

        // Docs
        $docsContent = $this->generateDocs($name, $key, $displayName, $capabilities, $credentials, $profiles);
        file_put_contents("{$basePath}/docs/README.md", $docsContent);
        $this->info("Created: {$basePath}/docs/README.md");

        $this->newLine();
        $this->info("Addon package [{$displayName}] generated at: {$basePath}");
        $this->line('');
        $this->line('Next steps:');
        $this->line("  1. cd {$basePath}");
        $this->line('  2. Implement the driver methods in src/' . $className . 'Driver.php');
        $this->line('  3. Install via: composer require frolax/payment-' . $vendorName);
        $this->line('  4. Gateway becomes available immediately (auto-discovered)');

        return self::SUCCESS;
    }

    // -------------------------------------------------------
    // Template generators
    // -------------------------------------------------------

    protected function generateDriverClass(
        string $className,
        string $key,
        string $displayName,
        array $capabilities,
        array $credentials,
        string $namespace,
        bool $isAddon,
    ): string {
        $interfaces = ['GatewayDriverContract'];
        $uses = ["use Frolax\\Payment\\Contracts\\GatewayDriverContract;"];
        $implements = '';
        $traitMethods = '';

        if (in_array('redirect', $capabilities)) {
            $interfaces[] = 'SupportsHostedRedirect';
            $uses[] = "use Frolax\\Payment\\Contracts\\SupportsHostedRedirect;";
        }
        if (in_array('webhook', $capabilities)) {
            $interfaces[] = 'SupportsWebhookVerification';
            $uses[] = "use Frolax\\Payment\\Contracts\\SupportsWebhookVerification;";
        }
        if (in_array('refund', $capabilities)) {
            $interfaces[] = 'SupportsRefund';
            $uses[] = "use Frolax\\Payment\\Contracts\\SupportsRefund;";
        }
        if (in_array('status', $capabilities)) {
            $interfaces[] = 'SupportsStatusQuery';
            $uses[] = "use Frolax\\Payment\\Contracts\\SupportsStatusQuery;";
        }

        $uses[] = "use Frolax\\Payment\\DTOs\\CanonicalPayload;";
        $uses[] = "use Frolax\\Payment\\DTOs\\CredentialsDTO;";
        $uses[] = "use Frolax\\Payment\\DTOs\\GatewayResult;";
        $uses[] = "use Frolax\\Payment\\Enums\\PaymentStatus;";
        $uses[] = "use Illuminate\\Http\\Request;";

        $implementsStr = implode(', ', $interfaces);
        $usesStr = implode("\n", array_unique($uses));

        $capabilitiesArray = implode(",\n            ", array_map(fn ($c) => "'{$c}'", $capabilities));

        $methods = $this->generateCapabilityMethods($capabilities);

        return <<<PHP
<?php

namespace {$namespace};

{$usesStr}

class {$className}Driver implements {$implementsStr}
{
    protected ?CredentialsDTO \$credentials = null;

    public function name(): string
    {
        return '{$key}';
    }

    public function setCredentials(CredentialsDTO \$credentials): static
    {
        \$this->credentials = \$credentials;

        return \$this;
    }

    public function capabilities(): array
    {
        return [
            {$capabilitiesArray},
        ];
    }

    // -------------------------------------------------------
    // Required: create + verify
    // -------------------------------------------------------

    public function create(CanonicalPayload \$payload, CredentialsDTO \$credentials): GatewayResult
    {
        // TODO: Map canonical payload -> {$displayName} API request
        // \$apiKey = \$credentials->get('key');
        // \$apiSecret = \$credentials->get('secret');

        // TODO: Make HTTP request to {$displayName} API

        // TODO: Return appropriate GatewayResult
        return new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: null,
            redirectUrl: null, // Set if using hosted redirect
            gatewayResponse: [],
        );
    }

    public function verify(Request \$request, CredentialsDTO \$credentials): GatewayResult
    {
        // TODO: Parse {$displayName} callback/return request
        // TODO: Verify payment status with {$displayName} API

        return new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayReference: null,
            gatewayResponse: [],
        );
    }

{$methods}
}

PHP;
    }

    protected function generateCapabilityMethods(array $capabilities): string
    {
        $methods = '';

        if (in_array('redirect', $capabilities)) {
            $methods .= <<<'PHP'

    // -------------------------------------------------------
    // Capability: Hosted Redirect
    // -------------------------------------------------------

    public function getRedirectUrl(GatewayResult $result): ?string
    {
        return $result->redirectUrl;
    }

PHP;
        }

        if (in_array('webhook', $capabilities)) {
            $methods .= <<<'PHP'

    // -------------------------------------------------------
    // Capability: Webhook Verification
    // -------------------------------------------------------

    public function verifyWebhookSignature(Request $request, CredentialsDTO $credentials): bool
    {
        // TODO: Implement webhook signature verification
        // $signature = $request->header('X-Signature');
        // $webhookSecret = $credentials->get('webhook_secret');

        return false;
    }

    public function parseWebhookEventType(Request $request): ?string
    {
        // TODO: Parse event type from webhook payload
        return $request->input('event_type');
    }

    public function parseWebhookGatewayReference(Request $request): ?string
    {
        // TODO: Parse gateway reference from webhook payload
        return $request->input('transaction_id');
    }

PHP;
        }

        if (in_array('refund', $capabilities)) {
            $methods .= <<<'PHP'

    // -------------------------------------------------------
    // Capability: Refund
    // -------------------------------------------------------

    public function refund(\Frolax\Payment\DTOs\CanonicalRefundPayload $payload, CredentialsDTO $credentials): GatewayResult
    {
        // TODO: Implement refund via gateway API

        return new GatewayResult(
            status: PaymentStatus::Refunded,
            gatewayResponse: [],
        );
    }

PHP;
        }

        if (in_array('status', $capabilities)) {
            $methods .= <<<'PHP'

    // -------------------------------------------------------
    // Capability: Status Query
    // -------------------------------------------------------

    public function status(\Frolax\Payment\DTOs\CanonicalStatusPayload $payload, CredentialsDTO $credentials): GatewayResult
    {
        // TODO: Query payment status from gateway API

        return new GatewayResult(
            status: PaymentStatus::Pending,
            gatewayResponse: [],
        );
    }

PHP;
        }

        return $methods;
    }

    protected function generateAddonProvider(
        string $className,
        string $key,
        string $displayName,
        array $capabilities,
        array $credentials,
        string $namespace,
    ): string {
        $capArray = implode(",\n            ", array_map(fn ($c) => "'{$c}'", $capabilities));
        $credArray = implode(",\n            ", array_map(fn ($k, $v) => "'{$k}' => '{$v}'", array_keys($credentials), array_values($credentials)));

        return <<<PHP
<?php

namespace {$namespace};

use Frolax\\Payment\\Contracts\\GatewayAddonContract;

class {$className}GatewayAddon implements GatewayAddonContract
{
    public function gatewayKey(): string
    {
        return '{$key}';
    }

    public function displayName(): string
    {
        return '{$displayName}';
    }

    public function driverClass(): string|callable
    {
        return {$className}Driver::class;
    }

    public function capabilities(): array
    {
        return [
            {$capArray},
        ];
    }

    public function credentialSchema(): array
    {
        return [
            {$credArray},
        ];
    }

    public function defaultConfig(): array
    {
        return [];
    }
}

PHP;
    }

    protected function generateAddonServiceProvider(string $className, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Frolax\\Payment\\Discovery\\GatewayAddonServiceProvider;

class {$className}ServiceProvider extends GatewayAddonServiceProvider
{
    public function gatewayAddon(): \\Frolax\\Payment\\Contracts\\GatewayAddonContract
    {
        return new {$className}GatewayAddon();
    }
}

PHP;
    }

    protected function generateAddonComposer(string $vendorName, string $name, string $className, string $namespace): string
    {
        $escapedNamespace = str_replace('\\', '\\\\', $namespace);

        return json_encode([
            'name' => "frolax/payment-{$vendorName}",
            'description' => "{$name} payment gateway driver for frolax/laravel-payments",
            'type' => 'library',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.4',
                'frolaxhq/laravel-payments' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    "{$escapedNamespace}\\" => 'src/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        "{$escapedNamespace}\\{$className}ServiceProvider",
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function generateTestFile(string $className, string $key, string $namespace, bool $isAddon): string
    {
        return <<<PHP
<?php

use Frolax\\Payment\\DTOs\\CanonicalPayload;
use Frolax\\Payment\\DTOs\\CredentialsDTO;
use Frolax\\Payment\\DTOs\\GatewayResult;
use {$namespace}\\{$className}Driver;

test('{$key} driver returns correct name', function () {
    \$driver = new {$className}Driver();
    expect(\$driver->name())->toBe('{$key}');
});

test('{$key} driver reports capabilities', function () {
    \$driver = new {$className}Driver();
    expect(\$driver->capabilities())->toBeArray();
});

test('{$key} driver can create a payment', function () {
    \$driver = new {$className}Driver();

    \$payload = CanonicalPayload::fromArray([
        'idempotency_key' => 'test-key-001',
        'order' => ['id' => 'ORD-001', 'description' => 'Test Order'],
        'money' => ['amount' => 100, 'currency' => 'USD'],
    ]);

    \$credentials = new CredentialsDTO(
        gateway: '{$key}',
        profile: 'test',
        credentials: ['key' => 'test_key', 'secret' => 'test_secret'],
    );

    \$result = \$driver->create(\$payload, \$credentials);

    expect(\$result)->toBeInstanceOf(GatewayResult::class);
});

PHP;
    }

    protected function generateConfigSnippet(string $key, array $profiles, array $credentials): string
    {
        $lines = ["<?php\n", "// Add to config/payments.php under 'gateways':\n", "// '{$key}' => ["];

        foreach ($profiles as $profile) {
            $lines[] = "//     '{$profile}' => [";
            foreach ($credentials as $credKey => $requirement) {
                $envKey = strtoupper($key . '_' . $profile . '_' . $credKey);
                $lines[] = "//         '{$credKey}' => env('{$envKey}'),";
            }
            $lines[] = '//     ],';
        }

        $lines[] = '// ],';

        return implode("\n", $lines) . "\n";
    }

    protected function generateAddonConfig(string $key, array $profiles, array $credentials): string
    {
        $profileConfig = '';
        foreach ($profiles as $profile) {
            $profileConfig .= "\n    '{$profile}' => [\n";
            foreach ($credentials as $credKey => $requirement) {
                $envKey = strtoupper($key . '_' . $profile . '_' . $credKey);
                $profileConfig .= "        '{$credKey}' => env('{$envKey}'),\n";
            }
            $profileConfig .= "    ],\n";
        }

        return "<?php\n\nreturn [{$profileConfig}];\n";
    }

    protected function generateDocs(string $name, string $key, string $displayName, array $capabilities, array $credentials, array $profiles): string
    {
        $capList = implode(', ', $capabilities);
        $profileList = implode(', ', $profiles);

        $credTable = "| Key | Required |\n|-----|----------|\n";
        foreach ($credentials as $credKey => $requirement) {
            $credTable .= "| {$credKey} | {$requirement} |\n";
        }

        return <<<MD
# {$displayName} Payment Gateway

## Overview
{$displayName} payment gateway driver for `frolax/laravel-payments`.

- **Gateway Key:** `{$key}`
- **Capabilities:** {$capList}
- **Profiles:** {$profileList}

## Credentials

{$credTable}

## Configuration

Add to `config/payments.php`:

```php
'gateways' => [
    '{$key}' => [
        'test' => [
            // Add credential keys from schema
        ],
        'live' => [
            // Add credential keys from schema
        ],
    ],
],
```

## Usage

```php
use Frolax\\Payment\\Facades\\Payment;

\$result = Payment::gateway('{$key}')->create([
    'order' => ['id' => 'ORD-001'],
    'money' => ['amount' => 100, 'currency' => 'USD'],
]);
```

MD;
    }

    protected function parseCredentials(string $input): array
    {
        $credentials = [];

        foreach (explode(',', $input) as $item) {
            $parts = explode(':', trim($item));
            $credentials[$parts[0]] = $parts[1] ?? 'required';
        }

        return $credentials;
    }
}
