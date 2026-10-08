<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Laranex\LaravelMoney\Facades\LaravelMoney as LaravelMoneyFacade;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\LaravelMoneyServiceProvider;

it('merges the default config', function () {
    expect(config('money.default_currency'))->toBe('USD');
});

it('lets the host application override the config', function () {
    config()->set('money.default_currency', 'MMK');
    $this->app->forgetInstance(LaravelMoney::class);

    expect(app(LaravelMoney::class)->defaultCurrency()->getCode())->toBe('MMK');
});

it('falls back to USD when the configured currency is empty', function () {
    config()->set('money.default_currency', '');
    $this->app->forgetInstance(LaravelMoney::class);

    expect(app(LaravelMoney::class)->defaultCurrency()->getCode())->toBe('USD');
});

it('binds the manager as a singleton behind the facade', function () {
    expect(app(LaravelMoney::class))->toBe(app(LaravelMoney::class))
        ->and(LaravelMoneyFacade::getFacadeRoot())->toBe(app(LaravelMoney::class));
});

it('publishes the config file under the package tags', function (string $tag) {
    $paths = ServiceProvider::pathsToPublish(LaravelMoneyServiceProvider::class, $tag);

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/money.php'))
        ->and(array_values($paths)[0])->toBe(config_path('money.php'));
})->with(['laravel-money', 'laravel-money-config']);

it('does not publish anything under an unknown tag', function () {
    expect(ServiceProvider::pathsToPublish(LaravelMoneyServiceProvider::class, 'laravel-money-views'))->toBe([]);
});

it('is discovered through the composer extra section', function () {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([LaravelMoneyServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases'])->toBe(['LaravelMoney' => LaravelMoneyFacade::class]);
});
