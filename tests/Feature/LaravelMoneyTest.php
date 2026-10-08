<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Facades\LaravelMoney;
use Laranex\LaravelMoney\LaravelMoney as LaravelMoneyManager;
use Money\Currency;
use Money\Money;

it('exposes the default currency from the config', function () {
    expect(LaravelMoney::defaultCurrency())->toEqual(new Currency('USD'));

    config()->set('money.default_currency', 'MMK');
    $this->app->forgetInstance(LaravelMoneyManager::class);
    LaravelMoney::clearResolvedInstance(LaravelMoneyManager::class);

    expect(LaravelMoney::defaultCurrency())->toEqual(new Currency('MMK'));
});

it('resolves currencies with the default as a fallback', function () {
    expect(LaravelMoney::currency())->toEqual(new Currency('USD'))
        ->and(LaravelMoney::currency(''))->toEqual(new Currency('USD'))
        ->and(LaravelMoney::currency('MMK'))->toEqual(new Currency('MMK'))
        ->and(LaravelMoney::currency(new Currency('EUR')))->toEqual(new Currency('EUR'));
});

it('makes Money from minor units', function () {
    expect(LaravelMoney::make(1050))->toEqual(Money::USD(1050))
        ->and(LaravelMoney::make('1050', 'MMK'))->toEqual(Money::MMK(1050))
        ->and(LaravelMoney::make(1, new Currency('EUR')))->toEqual(Money::EUR(1));
});

it('parses decimal strings using the currency subunit', function () {
    expect(LaravelMoney::parse('10.50'))->toEqual(Money::USD(1050))
        ->and(LaravelMoney::parse('2500', 'JPY'))->toEqual(Money::JPY(2500))
        ->and(LaravelMoney::parse('25.00', 'MMK'))->toEqual(Money::MMK(2500))
        ->and(LaravelMoney::parse('0.99', 'EUR'))->toEqual(Money::EUR(99));
});

it('formats Money as a decimal string', function () {
    expect(LaravelMoney::format(Money::USD(1050)))->toBe('10.50')
        ->and(LaravelMoney::format(Money::JPY(2500)))->toBe('2500')
        ->and(LaravelMoney::format(Money::MMK(2500)))->toBe('25.00');
});

it('works on the manager without the facade', function () {
    $manager = new LaravelMoneyManager('EUR');

    expect($manager->defaultCurrency())->toEqual(new Currency('EUR'))
        ->and($manager->make(500))->toEqual(Money::EUR(500))
        ->and($manager->format($manager->parse('5.00')))->toBe('5.00');
});
