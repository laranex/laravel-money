<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Money;

it('serializes to amount, currency, decimal and formatted strings', function (): void {
    $money = Money::of('1234.5', 'USD');

    expect($money->toArray())->toBe([
        'amount' => '123450',
        'currency' => 'USD',
        'decimal' => '1234.50',
        'formatted' => $money->format(),
    ])->and(json_encode($money))->toBe(json_encode($money->toArray()));
});

it('serializes the amount as a decimal when configured', function (): void {
    config()->set('money.serialization.amount', 'decimal');

    expect(Money::of('1.5', 'KWD')->toArray())->toBe([
        'amount' => '1.500',
        'currency' => 'KWD',
        'formatted' => Money::of('1.5', 'KWD')->format(),
    ]);
});

it('can leave out the decimal and formatted keys', function (): void {
    config()->set('money.serialization', ['amount' => 'minor', 'include_decimal' => false, 'include_formatted' => false]);

    expect(Money::of('99', 'JPY')->toArray())->toBe(['amount' => '99', 'currency' => 'JPY']);
});

it('falls back to defaults for missing serialization options', function (): void {
    config()->set('money.serialization', null);

    expect(array_keys(Money::of('1')->toArray()))->toBe(['amount', 'currency', 'decimal', 'formatted']);
});

it('rejects an invalid amount option', function (): void {
    config()->set('money.serialization.amount', 'cents');

    expect(fn () => Money::of('1')->toArray())->toThrow(InvalidMoneyException::class, 'The config value [money.serialization.amount] must be "minor" or "decimal".');
});
