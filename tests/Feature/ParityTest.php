<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Exceptions\CurrencyMismatchException;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\MoneyParseException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Laranex\LaravelMoney\Facades\LaravelMoney;
use Laranex\LaravelMoney\LaravelMoney as LaravelMoneyService;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currency;

// These tests mirror goravel-money's parity_test.go: the same input gives the
// same result, and the same kind of error, in both packages.

it('builds money from decimal amounts like goravel-money\'s Of', function (int|string $amount, ?string $currency, string $expected): void {
    expect((string) LaravelMoney::of($amount, $currency))->toBe($expected);
})->with([
    'decimal string' => ['1,234.50', 'USD', 'USD 1234.50'],
    'int is a whole amount' => [1234, 'JPY', 'JPY 1234'],
    'negative int' => [-5, 'USD', 'USD -5.00'],
    'default currency' => ['12.34', null, 'USD 12.34'],
]);

it('rejects invalid amounts like goravel-money\'s Of', function (): void {
    expect(Money::of('1.235', 'USD', Rounding::HalfUp)->toDecimal())->toBe('1.24')
        ->and(fn () => Money::of(12.5, 'USD'))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
        ->and(fn () => Money::of('1.234', 'USD'))->toThrow(MoneyParseException::class)
        ->and(fn () => LaravelMoney::of('1', 'XYZ'))->toThrow(UnknownCurrencyException::class);
});

it('builds money from minor units like goravel-money\'s OfMinor', function (): void {
    foreach ([123450, '123450', ' 123450 '] as $minor) {
        expect(Money::ofMinor($minor, 'USD')->toDecimal())->toBe('1234.50');
    }

    expect(LaravelMoney::ofMinor(-5, 'KWD')->toDecimal())->toBe('-0.005')
        ->and(fn () => Money::ofMinor(10.5, 'USD'))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
        ->and(fn () => Money::ofMinor('10.50', 'USD'))->toThrow(InvalidMoneyException::class, 'Use Money::of() for decimal amounts');
});

it('throws for invalid operands in equals()', function (): void {
    $price = Money::of('19.99', 'USD');

    expect($price->equals('19.99'))->toBeTrue()
        ->and($price->equals(Money::of('19.99', 'EUR')))->toBeFalse()
        ->and(fn () => $price->equals(19.99))->toThrow(InvalidMoneyException::class)
        ->and(fn () => $price->equals('19,99'))->toThrow(MoneyParseException::class)
        ->and(fn () => $price->equals('19.999'))->toThrow(MoneyParseException::class);
});

it('compares the precision in isSameCurrency()', function (): void {
    $iso = Money::of('100', 'MMK');

    config()->set('money.currencies', ['MMK' => 0]);
    app()->forgetInstance(CurrencyRegistry::class);
    app()->forgetInstance(LaravelMoneyService::class);
    $custom = Money::of('100', 'MMK');

    expect($custom->precision())->toBe(0)
        ->and($iso->isSameCurrency($custom))->toBeFalse()
        ->and($iso->equals($custom))->toBeFalse()
        ->and(fn () => $iso->plus($custom))->toThrow(CurrencyMismatchException::class)
        ->and(fn () => $iso->compare($custom))->toThrow(CurrencyMismatchException::class);
});

it('returns the currency as an object', function (): void {
    $currency = Money::of('1', 'usd')->currency();

    expect($currency)->toEqual(new Currency('USD'))
        ->and($currency->getCode())->toBe('USD')
        ->and((string) $currency)->toBe('USD');
});

it('gives tied leftover units to keys in ascending order', function (): void {
    // Two leftover cents, three equal remainders: the first keys in
    // ascending order get them, as in goravel-money's AllocateMap.
    $shares = Money::ofMinor(5, 'USD')->allocate(['platform' => 1, 'agent' => 1, 'owner' => 1]);

    expect(array_keys($shares))->toBe(['platform', 'agent', 'owner'])
        ->and($shares['agent']->toDecimal())->toBe('0.02')
        ->and($shares['owner']->toDecimal())->toBe('0.02')
        ->and($shares['platform']->toDecimal())->toBe('0.01');

    $byId = Money::ofMinor(2, 'USD')->allocate([10 => 1, 2 => 1, 7 => 1]);

    expect($byId[2]->toDecimal())->toBe('0.01')
        ->and($byId[7]->toDecimal())->toBe('0.01')
        ->and($byId[10]->toDecimal())->toBe('0.00');

    $mixed = Money::ofMinor(1, 'USD')->allocate(['a' => 1, 5 => 1]);

    expect($mixed[5]->toDecimal())->toBe('0.01')
        ->and($mixed['a']->toDecimal())->toBe('0.00');
});

it('requires a scale in percentageOf() and ratioOf()', function (string $method): void {
    $scale = (new ReflectionMethod(Money::class, $method))->getParameters()[1];

    expect($scale->getName())->toBe('scale')
        ->and($scale->isOptional())->toBeFalse();
})->with(['percentageOf', 'ratioOf']);

it('computes percentages and ratios with the given scale', function (): void {
    expect(Money::of('25')->percentageOf(Money::of('200'), 2))->toBe('12.50')
        ->and(Money::of('50')->ratioOf(Money::of('200'), 4))->toBe('0.2500')
        ->and(Money::of('1')->ratioOf(Money::of('3'), 0, Rounding::Ceiling))->toBe('1');
});

it('casts to the currency code and the decimal amount', function (): void {
    config()->set('app.locale', 'en');
    $price = Money::of('1234.5', 'USD');

    expect((string) $price)->toBe('USD 1234.50')
        ->and((string) Money::of('-0.005', 'KWD'))->toBe('KWD -0.005')
        ->and($price->format())->toBe(extension_loaded('intl') ? '$1,234.50' : 'USD 1234.50');
});

it('stores money only in a column of the same precision', function (): void {
    $iso = Money::of('100', 'MMK');

    config()->set('money.currencies', ['MMK' => 0]);
    app()->forgetInstance(CurrencyRegistry::class);
    app()->forgetInstance(LaravelMoneyService::class);

    $model = new class extends Model
    {
        protected $guarded = [];

        protected $casts = ['price' => AsMoney::class.':MMK'];
    };

    expect(fn () => $model->setAttribute('price', $iso))->toThrow(CurrencyMismatchException::class);
});
