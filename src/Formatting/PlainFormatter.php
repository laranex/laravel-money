<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Formatting;

/**
 * Formats money as "{CODE} {decimal}", e.g. "USD 1234.50". Used when the
 * intl extension is not installed.
 */
final class PlainFormatter implements Formatter
{
    public function format(string $decimal, string $currency, int $precision, string $locale): string
    {
        return $currency.' '.$decimal;
    }
}
