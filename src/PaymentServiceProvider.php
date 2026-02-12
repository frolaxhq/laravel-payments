<?php

namespace Frolax\Payment;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Frolax\Payment\Commands\PaymentCommand;

class PaymentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-payments')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_payments_table')
            ->hasCommand(PaymentCommand::class);
    }
}
