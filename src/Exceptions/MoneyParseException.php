<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

class MoneyParseException extends MoneyException
{
    public static function invalid(string $input): self
    {
        return new self(sprintf(
            'Cannot parse "%s" as an amount. Use digits with a dot as the decimal separator, e.g. "1234.50"; commas or spaces may only group thousands ("1,234.50").',
            $input,
        ));
    }

    public static function tooManyDecimals(string $input, string $currency, int $precision, int $given): self
    {
        return new self(sprintf(
            '%s allows %d decimal place%s, but "%s" has %d. Pass a rounding mode to round it, e.g. Money::of("%s", "%s", Rounding::HalfUp).',
            $currency,
            $precision,
            $precision === 1 ? '' : 's',
            $input,
            $given,
            $input,
            $currency,
        ));
    }
}
