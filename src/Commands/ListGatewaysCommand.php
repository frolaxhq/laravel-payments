<?php

namespace Frolax\Payment\Commands;

use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\GatewayRegistry;
use Illuminate\Console\Command;

class ListGatewaysCommand extends Command
{
    protected $signature = 'payments:gateways';

    protected $description = 'List all discovered payment gateways with capabilities and status';

    public function handle(GatewayRegistry $registry, CredentialsRepositoryContract $credentialsRepo): int
    {
        $gateways = $registry->all();

        if (empty($gateways)) {
            $this->warn('No gateways registered.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($gateways as $key => $entry) {
            $driver = is_string($entry['driver']) ? $entry['driver'] : '(callable)';
            $capabilities = implode(', ', array_map(function (string $cap) {
                // Show just the short name, e.g. "SupportsRecurring" → "Recurring"
                $shortName = class_basename($cap);

                return str_starts_with($shortName, 'Supports')
                    ? substr($shortName, 8)
                    : $shortName;
            }, $entry['capabilities'] ?: []) ?: ['—']);
            $currencies = empty($entry['supported_currencies'])
                ? 'All'
                : implode(', ', $entry['supported_currencies']);

            $profile = config('payments.profile', 'test');
            $hasEnvCreds = config("payments.gateways.{$key}.{$profile}") !== null;
            $hasDbCreds = $credentialsRepo->has($key, $profile);

            $configSource = match (true) {
                $hasDbCreds && $hasEnvCreds => 'env+db',
                $hasDbCreds => 'db',
                $hasEnvCreds => 'env',
                default => '—',
            };

            $addon = $entry['addon'];
            $rows[] = [
                $key,
                $entry['display_name'],
                $driver,
                $capabilities,
                $currencies,
                $configSource,
                $addon ? 'addon' : 'core',
            ];
        }

        $this->table(
            ['Key', 'Display Name', 'Driver', 'Capabilities', 'Currencies', 'Config Source', 'Type'],
            $rows
        );

        return self::SUCCESS;
    }
}
