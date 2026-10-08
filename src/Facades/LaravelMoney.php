<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Facades;

use Illuminate\Support\Facades\Facade;
use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Formatting\Formatter;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currency;
use Money\Money as MoneyPhp;

/**
 * @method static Money of(int|string|float $amount, Currency|string|null $currency = null, ?Rounding $rounding = null)
 * @method static Money ofMinor(int|string|float $minor, Currency|string|null $currency = null)
 * @method static Money zero(Currency|string|null $currency = null)
 * @method static Money fromMoneyPhp(MoneyPhp $money)
 * @method static CurrencyRegistry currencies()
 * @method static Currency currency(Currency|string|null $currency = null)
 * @method static Currency defaultCurrency()
 * @method static int precision(Currency|string|null $currency = null)
 * @method static Rounding rounding()
 * @method static string locale()
 * @method static string format(Money $money, ?string $locale = null)
 * @method static array{amount: 'minor'|'decimal', include_decimal: bool, include_formatted: bool} serialization()
 * @method static Formatter formatter()
 * @method static \Laranex\LaravelMoney\LaravelMoney useFormatter(?Formatter $formatter)
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
