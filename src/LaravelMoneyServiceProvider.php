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

        $this->app->singleton(CurrencyRegistry::class, function (Container $app): CurrencyRegistry {
            $custom = $app->make(ConfigRepository::class)->get('money.currencies', []);

            return new CurrencyRegistry(is_array($custom) ? $custom : []);
        });

        $this->app->singleton(LaravelMoney::class, fn (Container $app): LaravelMoney => new LaravelMoney(
            $app->make(ConfigRepository::class),
            $app->make(CurrencyRegistry::class),
        ));
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
            __DIR__.'/../config/money.php' => $this->app->configPath('money.php'),
        ], ['laravel-money', 'laravel-money-config']);
    }
}
