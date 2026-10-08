<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;

it('describes a value that is not Money', function () {
    $exception = InvalidMoneyException::notMoney('price', 100);

    expect($exception)->toBeInstanceOf(InvalidArgumentException::class)
        ->and($exception->getMessage())->toBe('The value for [price] must be an instance of Money\Money, int given.');
});

it('describes a currency mismatch', function () {
    expect(InvalidMoneyException::currencyMismatch('cost', 'MMK', 'USD')->getMessage())
        ->toBe('The attribute [cost] stores MMK amounts, USD given.');
});

it('describes an invalid stored amount', function () {
    expect(InvalidMoneyException::invalidStoredAmount('price', 1.5)->getMessage())
        ->toBe('The stored value for [price] must be an integer amount in minor units, float given. Store minor units in an integer column (e.g. 1050 for 10.50), not a decimal or float column.');
});

it('quotes an invalid stored string amount', function () {
    expect(InvalidMoneyException::invalidStoredAmount('price', '10.50')->getMessage())
        ->toBe('The stored value for [price] must be an integer amount in minor units, string "10.50" given. Store minor units in an integer column (e.g. 1050 for 10.50), not a decimal or float column.');
});
