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
use Frolax\Payment\Services\CurrencyConverter;
use Frolax\Payment\Services\InvoiceGenerator;
use Frolax\Payment\Services\RevenueAnalytics;
use Frolax\Payment\Services\RiskScorer;
use Frolax\Payment\Services\SandboxSimulator;
use Frolax\Payment\Services\SchemaValidator;
use Frolax\Payment\Services\TaxCalculator;
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
                'create_payment_gateways_table',
                'create_payment_gateway_credentials_table',
                'create_payments_table',
                'create_payment_attempts_table',
                'create_payment_webhook_events_table',
                'create_payment_refunds_table',
                'create_payment_logs_table',
                'create_payment_subscriptions_table',
                'create_payment_subscription_items_table',
                'create_payment_subscription_usage_table',
                'create_payment_methods_table',
                'create_payment_invoicing_tables',
                'create_payment_payout_tables',
                'create_payment_fraud_tables',
                'create_payment_coupon_tables',
                'create_payment_exchange_rates_table',
                'create_payment_links_table',
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

        // Register extended services
        $this->app->singleton(InvoiceGenerator::class);
        $this->app->singleton(TaxCalculator::class);
        $this->app->singleton(RiskScorer::class);
        $this->app->singleton(RevenueAnalytics::class);
        $this->app->singleton(CurrencyConverter::class);
        $this->app->singleton(WebhookRouter::class);
        $this->app->singleton(WebhookRetryPolicy::class);
        $this->app->singleton(SandboxSimulator::class);
        $this->app->singleton(SchemaValidator::class);
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
