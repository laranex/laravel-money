<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

use Laranex\LaravelMoney\Money;

class InvalidMoneyException extends MoneyException
{
    public static function float(float $value): self
    {
        return new self(sprintf(
            'Floats are not accepted for money (%s given) because they cannot hold decimal amounts exactly. Pass a string such as "%s", or an integer.',
            json_encode($value),
            json_encode($value),
        ));
    }

    public static function notMoney(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The value for [%s] must be an instance of %s or a decimal string such as "12.50", %s given.',
            $key,
            Money::class,
            get_debug_type($value),
        ));
    }

    public static function invalidStoredAmount(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The stored value for [%s] must be an integer amount in minor units, %s given. Store minor units in an integer column (e.g. 1050 for 10.50), or cast a DECIMAL column with AsMoney::decimal().',
            $key,
            is_string($value) ? sprintf('string "%s"', $value) : get_debug_type($value),
        ));
    }

    public static function invalidStoredDecimal(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The stored value for [%s] must be a decimal amount, %s given.',
            $key,
            is_string($value) ? sprintf('string "%s"', $value) : get_debug_type($value),
        ));
    }

    public static function impreciseStoredFloat(string $key, float $value): self
    {
        return new self(sprintf(
            'The database returned the float %s for [%s], which cannot be read exactly. SQLite stores DECIMAL columns as REAL; use integer storage (AsMoney::of()) instead.',
            json_encode($value),
            $key,
        ));
    }

    public static function invalidMinorAmount(string $value): self
    {
        return new self(sprintf(
            'Minor-unit amounts must be integers such as 1050 or "1050", "%s" given. Use Money::of() for decimal amounts.',
            $value,
        ));
    }

    public static function divisionByZero(): self
    {
        return new self('Cannot divide money by zero.');
    }

    public static function emptyAggregate(string $operation): self
    {
        return new self(sprintf('Money::%s() needs at least one Money instance.', $operation));
    }

    public static function notMoneyInstance(string $operation, mixed $value): self
    {
        return new self(sprintf('Money::%s() only accepts %s instances, %s given.', $operation, Money::class, get_debug_type($value)));
    }

    public static function invalidParts(int $parts): self
    {
        return new self(sprintf('Money can only be split into one or more parts, %d given.', $parts));
    }

    public static function invalidScale(int $scale): self
    {
        return new self(sprintf('The scale must be between 0 and %d, %d given.', Money::MAX_SCALE, $scale));
    }

    public static function invalidDecimals(int $decimals): self
    {
        return new self(sprintf('The decimals must be between -%d and %d, %d given.', Money::MAX_SCALE, Money::MAX_SCALE, $decimals));
    }

    public static function invalidRatios(string $reason): self
    {
        return new self('Cannot allocate money: '.$reason);
    }

    public static function invalidCastArgument(string $argument): self
    {
        return new self(sprintf(
            'Unknown money cast argument [%s]. Use AsMoney::of(), AsMoney::decimal() or AsMoney::currencyColumn() to build the cast.',
            $argument,
        ));
    }

    public static function invalidConfig(string $key, string $expected): self
    {
        return new self(sprintf('The config value [money.%s] must be %s.', $key, $expected));
    }
}
