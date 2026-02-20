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
use Frolax\Payment\Services\SchemaValidator;
use Frolax\Payment\Services\WebhookRetryPolicy;
use Frolax\Payment\Services\WebhookRouter;
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
                'create_payments_tables',
                'create_payment_subscription_tables',
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
        // Register PaymentConfig as singleton
        $this->app->singleton(PaymentConfig::class, function () {
            return new PaymentConfig;
        });

        // Register GatewayRegistry as singleton
        $this->app->singleton(GatewayRegistry::class, function () {
            return new GatewayRegistry;
        });

        // Register CredentialsRepository based on config
        $this->app->singleton(CredentialsRepositoryContract::class, function () {
            $storage = config('payments.credential_storage', 'env');

            return match ($storage) {
                'database' => new DatabaseCredentialsRepository,
                'composite' => CompositeCredentialsRepository::default(),
                default => new EnvCredentialsRepository,
            };
        });

        // Register PaymentLogger
        $this->app->singleton(PaymentLoggerContract::class, function () {
            return new PaymentLogger;
        });

        // Register Payment manager
        $this->app->singleton(Payment::class, function ($app) {
            return new Payment(
                registry: $app->make(GatewayRegistry::class),
                credentialsRepo: $app->make(CredentialsRepositoryContract::class),
                logger: $app->make(PaymentLoggerContract::class),
                config: $app->make(PaymentConfig::class),
            );
        });

        // Register SubscriptionManager
        $this->app->singleton(SubscriptionManager::class, function ($app) {
            return new SubscriptionManager(
                registry: $app->make(GatewayRegistry::class),
                credentialsRepo: $app->make(CredentialsRepositoryContract::class),
                logger: $app->make(PaymentLoggerContract::class),
                config: $app->make(PaymentConfig::class),
            );
        });

        // Register RefundManager
        $this->app->singleton(RefundManager::class, function ($app) {
            return new RefundManager(
                registry: $app->make(GatewayRegistry::class),
                credentialsRepo: $app->make(CredentialsRepositoryContract::class),
                logger: $app->make(PaymentLoggerContract::class),
                config: $app->make(PaymentConfig::class),
            );
        });

        // Register core services
        $this->app->singleton(WebhookRouter::class);
        $this->app->singleton(WebhookRetryPolicy::class);
        $this->app->singleton(SchemaValidator::class);
    }

    public function packageBooted(): void
    {
        // Routes are registered via hasRoute(); the route file itself checks config.
    }
}
