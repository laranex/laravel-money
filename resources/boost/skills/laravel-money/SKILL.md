---
name: laravel-money
description: >
  Work with money in a Laravel app using laranex/laravel-money: the immutable Laranex\LaravelMoney\Money value object, Eloquent casts (AsMoney), exact arithmetic, percentages, allocation, rounding and formatting with the correct precision for every currency.
license: MIT
metadata:
  author: Nay Thu Khant
---

# Laravel Money

## When to use

Use this skill when a Laravel app stores, calculates or displays prices, balances, totals, taxes, discounts or payouts with laranex/laravel-money. Money is exact: integer minor units (or exact DECIMAL strings) in the database, `Laranex\LaravelMoney\Money` in PHP, never floats. The currency decides the precision (USD 2, JPY 0, KWD 3, MMK 2), so never hard-code "divide by 100".

## Install

```bash
composer require laranex/laravel-money
```

Requires ext-bcmath. Install ext-intl for locale-aware formatting (`$1,234.50`); without it money formats as `USD 1234.50`.

## Configure

- `.env`: `MONEY_CURRENCY=USD` (ISO 4217 code or a custom currency; default `USD`)
- publish only to change it: `php artisan vendor:publish --tag="laravel-money-config"`
- `config/money.php`:
  - `default_currency`
  - `rounding`: `half_up` (default), `half_down`, `half_even`, `half_odd`, `half_positive_infinity`, `half_negative_infinity`, `ceiling`, `floor`
  - `currencies`: custom codes and decimals, e.g. `['PTS' => 0]` (they override ISO precision)
  - `locale`: `null` uses the app locale
  - `serialization`: `amount` (`minor` or `decimal`), `include_decimal`, `include_formatted`

## Use

### Build money

```php
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;

Money::of('1,234.50', 'USD');                 // decimal string; commas/spaces group thousands
Money::of(1234, 'JPY');                       // int = whole units
Money::of('1.235', 'USD', Rounding::HalfUp);  // round extra decimals (without a Rounding it throws)
Money::ofMinor(123450, 'USD');                // minor units
Money::zero('KWD');
money('12.34', 'USD');                        // helper, same as Money::of()
Money::fromMoneyPhp($moneyphp);               // and $money->toMoneyPhp()
```

The currency argument is optional (defaults to `money.default_currency`).

### Store in Eloquent

```php
use Laranex\LaravelMoney\Casts\AsMoney;

protected function casts(): array
{
    return [
        'price' => Money::class,                              // bigint minor units, default currency
        'cost' => AsMoney::of('USD'),                         // bigint, always USD
        'balance' => AsMoney::currencyColumn('currency'),     // bigint, currency from the "currency" column
        'fee' => AsMoney::decimal('USD'),                     // DECIMAL column
        'total' => AsMoney::decimal(currencyColumn: 'currency'),
    ];
}
```

- Laravel 10 only reads the `$casts` property, so use strings there: `AsMoney::class.':USD'`, `AsMoney::class.':currency_column=currency'`, `AsMoney::class.':decimal,USD'`
- migrate integer casts as `$table->bigInteger('price')->nullable();`
- assign a `Money`, a moneyphp `Money\Money`, a decimal string (`'12.50'`) or `null`
- `currencyColumn` fills an empty currency column and throws `CurrencyMismatchException` when it holds another currency

### Calculate

```php
$price->plus($shipping, '2.50');       // int/string operands are decimal amounts in the same currency
$price->minus($discount);
$price->times('1.5');                  // rounds with money.rounding, or pass a Rounding
$price->dividedBy(3, Rounding::Floor);
$price->mod('0.25');
$price->negated();
$price->absolute();
$price->roundTo(0);                    // whole units, precision kept

$price->percent('7.5');                // 7.5% of the price
$price->addPercent('8.875');           // tax
$price->subtractPercent(15);           // discount
$part->percentageOf($total);           // "12.50" (string, scale 2)
$part->ratioOf($total, 4);             // "0.1250"

Money::of('100')->split(3);            // 33.34, 33.33, 33.33 (no minor unit lost)
$payout->allocate(['owner' => 70, 'agent' => 30]); // keys kept

Money::sum($order->items->pluck('price')); // also min(), max(), avg($items, Rounding::HalfEven)
```

### Compare and read

```php
$price->equals('19.99');
$price->greaterThan($other);      // also greaterThanOrEqual, lessThan, lessThanOrEqual, compare
$price->isZero();                 // also isPositive, isNegative, isSameCurrency
$price->amount();                 // "1999" minor units
$price->toDecimal();              // "19.99"
$price->currency();               // "USD"
$price->precision();              // 2
$price->format();                 // "$19.99" (exact, any size); format('de_DE') for a locale
(string) $price;                  // same as format()
$price->toArray();                // ['amount' => '1999', 'currency' => 'USD', 'decimal' => '19.99', 'formatted' => '$19.99']
```

The `LaravelMoney` facade offers `of`, `ofMinor`, `zero`, `fromMoneyPhp`, `currency`, `defaultCurrency`, `precision`, `rounding`, `locale`, `format` and `currencies`.

### Errors

All extend `Laranex\LaravelMoney\Exceptions\MoneyException` (an `InvalidArgumentException`): `MoneyParseException` (bad input or too many decimals), `UnknownCurrencyException`, `CurrencyMismatchException`, `InvalidMoneyException` (floats, division by zero, invalid ratios or cast values).

## Test your app

There is no fake to set up: `Money` is a plain value object. Assert on strings, not floats:

```php
expect($order->fresh()->total->toDecimal())->toBe('1344.06')
    ->and($order->total->currency())->toBe('USD');

$this->assertTrue($invoice->total->equals('99.90'));
```

Set `config(['money.default_currency' => 'JPY'])` in a test to exercise another currency.

## Avoid

- floats anywhere: `Money::of(12.5)`, `->times(1.1)`, `->percent(7.5)` throw `InvalidMoneyException`; pass strings
- doing math on `amount()` yourself or assuming two decimals; use `times`, `dividedBy`, `percent`, `split`
- adding or comparing different currencies; convert first (`CurrencyMismatchException`)
- assigning ints to cast attributes (`1050` is ambiguous); assign `Money` or a decimal string
- FLOAT columns for money; use bigint minor units, or `AsMoney::decimal()` with a DECIMAL column
- changing a currency's precision in `money.currencies` after amounts are stored in it
