<?php

declare(strict_types=1);

use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Money\Currencies;
use Money\Currency;

it('knows ISO 4217 precisions', function (string $code, int $precision): void {
    expect((new CurrencyRegistry)->precision($code))->toBe($precision);
})->with([['USD', 2], ['MMK', 2], ['JPY', 0], ['KWD', 3], ['BHD', 3], ['CLP', 0], ['EUR', 2]]);

it('adds custom currencies and lets them override ISO precision', function (): void {
    $registry = new CurrencyRegistry(['pts' => 0, 'MMK' => 0, ' GEM ' => 4]);

    expect($registry->precision('PTS'))->toBe(0)
        ->and($registry->precision('MMK'))->toBe(0)
        ->and($registry->precision(new Currency('GEM')))->toBe(4)
        ->and($registry->custom())->toBe(['PTS' => 0, 'MMK' => 0, 'GEM' => 4])
        ->and($registry->currencies())->toBeInstanceOf(Currencies::class)
        ->and($registry->currencies()->contains(new Currency('PTS')))->toBeTrue();
});

it('validates currency codes', function (): void {
    $registry = new CurrencyRegistry;

    expect($registry->has('usd'))->toBeTrue()
        ->and($registry->has(new Currency('USD')))->toBeTrue()
        ->and($registry->has('XYZ'))->toBeFalse()
        ->and($registry->has(''))->toBeFalse()
        ->and($registry->resolve(' eur ')->getCode())->toBe('EUR')
        ->and(fn () => $registry->resolve('XYZ'))->toThrow(UnknownCurrencyException::class, 'Unknown currency [XYZ]. Use an ISO 4217 code')
        ->and(fn () => $registry->resolve(''))->toThrow(UnknownCurrencyException::class)
        ->and(fn () => $registry->precision('XYZ'))->toThrow(UnknownCurrencyException::class);
});

it('rejects invalid custom currency config', function (mixed $config): void {
    expect(fn () => new CurrencyRegistry($config))->toThrow(InvalidMoneyException::class, 'The config value [money.currencies] must be a map of currency codes');
})->with([
    'negative precision' => [['PTS' => -1]],
    'string precision' => [['PTS' => '2']],
    'list' => [['PTS']],
    'empty code' => [['' => 2]],
]);
