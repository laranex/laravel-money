<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

use InvalidArgumentException;
use Money\Money;

class InvalidMoneyException extends InvalidArgumentException
{
    public static function notMoney(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The value for [%s] must be an instance of %s, %s given.',
            $key,
            Money::class,
            get_debug_type($value),
        ));
    }

    public static function currencyMismatch(string $key, string $expected, string $actual): self
    {
        return new self(sprintf(
            'The attribute [%s] stores %s amounts, %s given.',
            $key,
            $expected,
            $actual,
        ));
    }

    public static function invalidStoredAmount(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The stored value for [%s] must be an integer amount in minor units, %s given.',
            $key,
            get_debug_type($value),
        ));
    }
}
