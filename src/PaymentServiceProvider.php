<?php

namespace Frolax\Payment;

use Frolax\Payment\Commands\ListGatewaysCommand;
use Frolax\Payment\Commands\MakeGatewayCommand;
use Frolax\Payment\Commands\ReplayWebhookCommand;
use Frolax\Payment\Commands\SyncCredentialsCommand;
use Frolax\Payment\Contracts\CredentialsRepositoryContract;
use Frolax\Payment\Contracts\PaymentLoggerContract;
use Frolax\Payment\Credentials\CompositeCredentialsRepository;
use Frolax\Payment\Credentials\DatabaseCredentialsRepository;
use Frolax\Payment\Credentials\EnvCredentialsRepository;
use Frolax\Payment\Logging\PaymentLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PaymentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-payments')
            ->hasConfigFile('payments')
            ->hasMigrations([
                'create_payment_gateways_table',
                'create_payment_gateway_credentials_table',
                'create_payments_table',
                'create_payment_attempts_table',
                'create_payment_webhook_events_table',
                'create_payment_refunds_table',
                'create_payment_logs_table',
            ])
            ->hasRoute('web')
            ->hasCommands([
                MakeGatewayCommand::class,
                ListGatewaysCommand::class,
                SyncCredentialsCommand::class,
                ReplayWebhookCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register GatewayRegistry as singleton
        $this->app->singleton(GatewayRegistry::class, function () {
            return new GatewayRegistry();
        });

        // Register CredentialsRepository based on config
        $this->app->singleton(CredentialsRepositoryContract::class, function () {
            $storage = config('payments.credential_storage', 'env');

            return match ($storage) {
                'database' => new DatabaseCredentialsRepository(),
                'composite' => CompositeCredentialsRepository::default(),
                default => new EnvCredentialsRepository(),
            };
        });

        // Register PaymentLogger
        $this->app->singleton(PaymentLoggerContract::class, function () {
            return new PaymentLogger();
        });

        // Register Payment manager
        $this->app->singleton(Payment::class, function ($app) {
            return new Payment(
                registry: $app->make(GatewayRegistry::class),
                credentialsRepo: $app->make(CredentialsRepositoryContract::class),
                logger: $app->make(PaymentLoggerContract::class),
            );
        });
    }

    public function packageBooted(): void
    {
        // Register routes conditionally
        if (!config('payments.routes.enabled', true)) {
            // Routes are registered via hasRoute(), but we can't conditionally
            // remove them easily. The route file itself checks config.
        }
    }
}
