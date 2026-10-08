<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Facades\LaravelMoney as LaravelMoneyFacade;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\LaravelMoneyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelMoneyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('money.currencies', ['PTS' => 0]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('cost')->nullable();
            $table->bigInteger('deposit')->nullable();
            $table->bigInteger('points')->nullable();
            $table->bigInteger('yen')->nullable();
            $table->decimal('fee', 20, 4)->nullable();
            $table->decimal('kwd', 20, 3)->nullable();
            $table->string('currency', 3)->nullable();
            $table->bigInteger('balance')->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Rebuild the money services after changing config in a test.
     */
    protected function refreshMoney(): void
    {
        $this->app->forgetInstance(CurrencyRegistry::class);
        $this->app->forgetInstance(LaravelMoney::class);
        LaravelMoneyFacade::clearResolvedInstance(LaravelMoney::class);
    }
}
