<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Facades;

use Illuminate\Support\Facades\Facade;
use Money\Currency;
use Money\Money;

/**
 * @method static Currency defaultCurrency()
 * @method static Currency currency(Currency|string|null $currency = null)
 * @method static Money make(int|string $amount, Currency|string|null $currency = null)
 * @method static Money parse(string $decimal, Currency|string|null $currency = null)
 * @method static string format(Money $money)
 *
 * @see \Laranex\LaravelMoney\LaravelMoney
 */
class LaravelMoney extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laranex\LaravelMoney\LaravelMoney::class;
    }
}
