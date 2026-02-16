<?php

namespace Frolax\Payment\Commands;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\GatewayRegistry;
use Illuminate\Console\Command;

class SyncCredentialsCommand extends Command
{
    protected $signature = 'payments:credentials:sync
                            {--gateway= : Specific gateway to check}
                            {--profile= : Specific profile (test/live)}
                            {--tenant= : Tenant ID to check}';

    protected $description = 'Validate required credentials exist for gateways';

    public function handle(GatewayRegistry $registry, CredentialsRepositoryContract $credentialsRepo): int
    {
        $gatewayFilter = $this->option('gateway');
        $profile = $this->option('profile') ?? config('payments.profile', 'test');
        $tenantId = $this->option('tenant');
        $context = $tenantId ? ['tenant_id' => $tenantId] : [];

        $gateways = $registry->all();
        $issues = [];

        foreach ($gateways as $key => $entry) {
            if ($gatewayFilter && $key !== $gatewayFilter) {
                continue;
            }

            $schema = $registry->credentialSchema($key);

            if (empty($schema)) {
                $this->line("  <info>{$key}</info>: No credential schema defined — skipping");
                continue;
            }

            $creds = $credentialsRepo->get($key, $profile, $context);

            if (!$creds) {
                $this->error("  {$key}: No credentials found for profile [{$profile}]" . ($tenantId ? " tenant [{$tenantId}]" : ''));
                $issues[] = $key;
                continue;
            }

            $missingKeys = [];
            foreach ($schema as $schemaKey => $requirement) {
                if ($requirement === 'required' && empty($creds->get($schemaKey))) {
                    $missingKeys[] = $schemaKey;
                }
            }

            if (!empty($missingKeys)) {
                $this->error("  {$key}: Missing required keys: " . implode(', ', $missingKeys));
                $issues[] = $key;
            } else {
                $this->info("  {$key}: ✓ All required credentials present");
            }
        }

        if (!empty($issues)) {
            $this->newLine();
            $this->error('Some gateways have missing credentials.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All gateway credentials are valid.');

        return self::SUCCESS;
    }
}
