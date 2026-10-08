<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;

/**
 * Builds money casts. Every builder returns a cast string, so it works in
 * a $casts property and in a casts() method on Laravel 10 through 13.
 *
 *     'price' => AsMoney::class,                       // integer minor units, default currency
 *     'price' => AsMoney::of('USD'),                   // integer minor units, always USD
 *     'price' => AsMoney::currencyColumn('currency'),  // currency read from / written to another column
 *     'price' => AsMoney::decimal('USD'),              // DECIMAL column holding "12.50"
 *     'price' => AsMoney::decimal(currencyColumn: 'currency'),
 */
final class AsMoney implements Castable
{
    /**
     * Integer minor units in a fixed currency.
     */
    public static function of(string $currency): string
    {
        return self::class.':'.$currency;
    }

    /**
     * A DECIMAL column, in a fixed currency, a currency column, or the default currency.
     */
    public static function decimal(?string $currency = null, ?string $currencyColumn = null): string
    {
        return self::build(['decimal', $currency, $currencyColumn === null ? null : 'currency_column='.$currencyColumn]);
    }

    /**
     * Integer minor units whose currency is stored in another column.
     */
    public static function currencyColumn(string $column, bool $decimal = false): string
    {
        return self::build([$decimal ? 'decimal' : null, 'currency_column='.$column]);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public static function castUsing(array $arguments): MoneyCast
    {
        $currency = null;
        $decimal = false;
        $currencyColumn = null;

        foreach ($arguments as $argument) {
            $argument = trim(is_scalar($argument) ? (string) $argument : '');

            if ($argument === '' || $argument === 'integer') {
                continue;
            }

            if ($argument === 'decimal') {
                $decimal = true;
            } elseif (str_starts_with($argument, 'currency_column=')) {
                $currencyColumn = substr($argument, strlen('currency_column='));
            } elseif (str_contains($argument, '=')) {
                throw InvalidMoneyException::invalidCastArgument($argument);
            } else {
                $currency = $argument;
            }
        }

        return new MoneyCast($currency, $decimal, $currencyColumn);
    }

    /**
     * @param  list<string|null>  $arguments
     */
    private static function build(array $arguments): string
    {
        $arguments = array_filter($arguments, static fn (?string $argument): bool => $argument !== null && $argument !== '');

        return $arguments === [] ? self::class : self::class.':'.implode(',', $arguments);
    }
}
