<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currency;

if (! function_exists('money')) {
    /**
     * Money from a decimal amount: money('12.34', 'USD').
     */
    function money(int|string|float $amount, Currency|string|null $currency = null, ?Rounding $rounding = null): Money
    {
        return Money::of($amount, $currency, $rounding);
    }
}
