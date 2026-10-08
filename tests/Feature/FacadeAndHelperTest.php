<?php

declare(strict_types=1);

use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Facades\LaravelMoney;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currency;
use Money\Money as MoneyPhp;

it('builds money through the facade', function (): void {
    expect(LaravelMoney::of('12.34', 'USD')->amount())->toBe('1234')
        ->and(LaravelMoney::of('1.005', 'USD', Rounding::HalfUp)->amount())->toBe('101')
        ->and(LaravelMoney::ofMinor(1234, 'JPY')->toDecimal())->toBe('1234')
        ->and(LaravelMoney::zero('KWD')->toDecimal())->toBe('0.000')
        ->and(LaravelMoney::fromMoneyPhp(MoneyPhp::EUR(5))->toDecimal())->toBe('0.05');
});

it('exposes currencies, precision, rounding and locale', function (): void {
    expect(LaravelMoney::defaultCurrency())->toEqual(new Currency('USD'))
        ->and(LaravelMoney::currency())->toEqual(new Currency('USD'))
        ->and(LaravelMoney::currency(''))->toEqual(new Currency('USD'))
        ->and(LaravelMoney::currency('mmk'))->toEqual(new Currency('MMK'))
        ->and(LaravelMoney::precision())->toBe(2)
        ->and(LaravelMoney::precision('KWD'))->toBe(3)
        ->and(LaravelMoney::precision('PTS'))->toBe(0)
        ->and(LaravelMoney::rounding())->toBe(Rounding::HalfUp)
        ->and(LaravelMoney::currencies())->toBeInstanceOf(CurrencyRegistry::class)
        ->and(LaravelMoney::format(Money::of('1', 'USD'), 'en_US'))->toBe(Money::of('1', 'USD')->format('en_US'));
});

it('falls back to USD and half up when the config is empty', function (): void {
    config()->set('money.default_currency', '');
    config()->set('money.rounding', null);
    config()->set('money.locale', null);
    config()->set('app.locale', null);

    expect(LaravelMoney::defaultCurrency()->getCode())->toBe('USD')
        ->and(LaravelMoney::rounding())->toBe(Rounding::HalfUp)
        ->and(LaravelMoney::locale())->toBe('en');
});

it('provides the money() helper', function (): void {
    expect(money('12.34', 'USD'))->toBeInstanceOf(Money::class)
        ->and(money('12.34', 'USD')->amount())->toBe('1234')
        ->and(money(5)->toDecimal())->toBe('5.00')
        ->and(money('0.125', 'USD', Rounding::Floor)->amount())->toBe('12');
});
