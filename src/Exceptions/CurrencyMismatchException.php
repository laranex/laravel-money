<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

use Laranex\LaravelMoney\Money;

class CurrencyMismatchException extends MoneyException
{
    public static function between(Money $left, Money $right): self
    {
        return new self(sprintf(
            'Cannot combine %s %s with %s %s: the amounts are in different currencies. Convert one of them first (for example with a moneyphp Converter and an exchange rate).',
            $left->currency(),
            $left->toDecimal(),
            $right->currency(),
            $right->toDecimal(),
        ));
    }

    public static function forAttribute(string $key, string $expected, Money $given): self
    {
        return new self(sprintf(
            'The attribute [%s] stores %s amounts, but %s %s was given. Convert it to %s first.',
            $key,
            $expected,
            $given->currency(),
            $given->toDecimal(),
            $expected,
        ));
    }

    public static function forCurrencyColumn(string $key, string $column, string $expected, Money $given): self
    {
        return new self(sprintf(
            'The attribute [%s] reads its currency from [%s], which holds %s, but %s %s was given. Convert the amount, or change [%s] first.',
            $key,
            $column,
            $expected,
            $given->currency(),
            $given->toDecimal(),
            $column,
        ));
    }
}
