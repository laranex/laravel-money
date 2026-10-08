<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Facades\LaravelMoney as LaravelMoneyFacade;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\LaravelMoneyServiceProvider;

it('merges the default config', function (): void {
    $config = require __DIR__.'/../../config/money.php';

    expect($config)->toBe([
        'default_currency' => 'USD',
        'rounding' => 'half_up',
        'currencies' => [],
        'locale' => null,
        'serialization' => [
            'amount' => 'minor',
            'include_decimal' => true,
            'include_formatted' => true,
        ],
    ])->and(config('money.default_currency'))->toBe('USD')
        ->and(config('money.rounding'))->toBe('half_up')
        ->and(config('money.serialization.amount'))->toBe('minor');
});

it('lets the host application override the config', function (): void {
    config()->set('money.default_currency', 'MMK');
    config()->set('money.currencies', ['GEM' => 4]);
    $this->refreshMoney();

    expect(app(LaravelMoney::class)->defaultCurrency()->getCode())->toBe('MMK')
        ->and(app(CurrencyRegistry::class)->precision('GEM'))->toBe(4)
        ->and(app(CurrencyRegistry::class)->has('PTS'))->toBeFalse();
});

it('ignores a currencies value that is not an array', function (): void {
    config()->set('money.currencies', 'PTS');
    $this->refreshMoney();

    expect(app(CurrencyRegistry::class)->custom())->toBe([]);
});

it('binds the registry and the manager as singletons behind the facade', function (): void {
    expect(app(CurrencyRegistry::class))->toBe(app(CurrencyRegistry::class))
        ->and(app(LaravelMoney::class))->toBe(app(LaravelMoney::class))
        ->and(app(LaravelMoney::class)->currencies())->toBe(app(CurrencyRegistry::class))
        ->and(LaravelMoneyFacade::getFacadeRoot())->toBe(app(LaravelMoney::class));
});

it('publishes the config file under the package tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(LaravelMoneyServiceProvider::class, $tag);

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/money.php'))
        ->and(array_values($paths)[0])->toBe(config_path('money.php'));
})->with(['laravel-money', 'laravel-money-config']);

it('does not publish anything under an unknown tag', function (): void {
    expect(ServiceProvider::pathsToPublish(LaravelMoneyServiceProvider::class, 'laravel-money-views'))->toBe([]);
});

it('is discovered through the composer extra section', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([LaravelMoneyServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases'])->toBe(['LaravelMoney' => LaravelMoneyFacade::class])
        ->and($composer['autoload']['files'])->toBe(['src/helpers.php'])
        ->and($composer['require']['moneyphp/money'])->toBe('^4.0');
});
