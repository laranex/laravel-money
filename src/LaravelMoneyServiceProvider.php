<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class LaravelMoneyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/money.php', 'money');

        $this->app->singleton(LaravelMoney::class, function (Container $app): LaravelMoney {
            $currency = $app->make(ConfigRepository::class)->get('money.default_currency', 'USD');

            return new LaravelMoney(is_string($currency) && $currency !== '' ? $currency : 'USD');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/money.php' => config_path('money.php'),
        ], ['laravel-money', 'laravel-money-config']);
    }
}
