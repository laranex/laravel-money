<?php

namespace Laranex\LaravelMoney;

use Laranex\LaravelMoney\Commands\LaravelMoneyCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMoneyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-money');
        /*
        ->hasConfigFile()
        ->hasViews()
        ->hasMigration('create_laravel_money_table')
        ->hasCommand(LaravelMoneyCommand::class);
        */
    }
}
