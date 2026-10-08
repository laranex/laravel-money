<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Formatting;

interface Formatter
{
    /**
     * Format an exact decimal amount (e.g. "-1234.50") for display.
     *
     * @param  numeric-string  $decimal
     */
    public function format(string $decimal, string $currency, int $precision, string $locale): string;
}
