<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('cost')->nullable();
            $table->bigInteger('deposit')->nullable();
            $table->timestamps();
        });
    }
}
