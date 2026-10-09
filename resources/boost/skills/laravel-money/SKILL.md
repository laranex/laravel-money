---
name: laravel-money
description: >
  Work with money in a Laravel app using laranex/laravel-money: the immutable Laranex\LaravelMoney\Money value object, Eloquent casts, exact arithmetic, percentages, allocation, rounding and formatting with the correct precision for every currency.
license: MIT
metadata:
  author: Nay Thu Khant
---

# Laravel Money

## When to use

Use this skill when a Laravel app creates, calculates, formats, stores or serializes prices, balances, totals, taxes, discounts or payouts. Keep every amount as `Laranex\LaravelMoney\Money` from request to database to JSON, never as a float. The currency decides the precision (USD 2, JPY 0, KWD 3, MMK 2), so never hard-code "divide by 100".

## Install

```bash
composer require laranex/laravel-money
```

Requires PHP 8.1+ with ext-bcmath and Laravel 10 to 13. The service provider, the `LaravelMoney` facade and the `money()` helper register themselves. Install ext-intl for locale-aware formatting (`$1,234.50`); without it, `format()` returns `USD 1234.50`.

## Configure

`config/money.php` (`money.*`; publish it with `php artisan vendor:publish --tag="laravel-money-config"` only to change it):

- `default_currency`: `env('MONEY_CURRENCY', 'USD')`, used whenever no currency is given.
- `rounding`: `half_up` (default), `half_down`, `half_even`, `half_odd`, `half_positive_infinity`, `half_negative_infinity`, `ceiling` or `floor`.
- `currencies`: custom currencies as code => decimal places, e.g. `'PTS' => 0`; they also override ISO precision (`'MMK' => 0`).
- `locale`: formatting locale such as `en`, `de_DE` or `my_MM`; `null` uses the app locale.
- `serialization`: `amount` (`minor` or `decimal`), `include_decimal`, `include_formatted`.

An unknown default currency or an invalid value throws the first time it is used.

## Use

Import `Laranex\LaravelMoney\Money`, `Laranex\LaravelMoney\Rounding`, `Laranex\LaravelMoney\Exceptions\MoneyException` and, for casts, `Laranex\LaravelMoney\Casts\AsMoney`.

### Build money

```php
$price = Money::of($request->input('price'));       // "1,234.50" in the default currency
$yen = Money::of(1500, 'JPY');                      // an int is a whole amount
$cents = Money::ofMinor(123450, 'USD');             // from minor units
$rounded = Money::of('1.235', 'USD', Rounding::HalfUp); // 1.24
$zero = Money::zero('KWD');
```

- Parsing is strict: `'12,50'`, `'.5'`, `'1e3'`, mixed separators (`'1 234,567'`) and irregular groups (`'1,234,56,789'`) throw `MoneyParseException`; grouping needs one separator throughout, in Western groups of three (`'1,234,567'`) or Indian grouping (`'12,34,567'`); `'1.234'` USD throws `MoneyParseException` unless a rounding mode is passed. `'12.500'` is fine.
- The facade and the helper do the same: `LaravelMoney::of('12.34', 'USD')`, `money('12.34', 'USD')`.
- Read with `amount()` (minor units string), `toDecimal()` (`'1234.50'`), `currency()->getCode()`, `precision()`, `isZero()`, `isPositive()`, `isNegative()`.

### Calculate

```php
$total = $price->plus($shipping, '2.50');      // Money, decimal strings or ints
$total = $total->minus($discount);
$tax = $price->times('0.0825');                // multipliers are strings or ints
$each = $price->dividedBy(3, Rounding::Floor); // optional rounding per call
$withTax = $price->addPercent('8.875');
$off = $price->subtractPercent(15);
$pct = $part->percentageOf($total, 2);         // "12.50"
$parts = $price->split(3);                     // 33.34, 33.33, 33.33
$shares = $price->allocate(['owner' => 70, 'agent' => 30]);
$sum = Money::sum($prices);                    // also min, max, avg
$cash = $price->roundTo(0);                    // 12.00
$price->greaterThan('10');                     // also lessThan, compare...
```

- Mixing currencies throws `CurrencyMismatchException`; floats throw `InvalidMoneyException`; dividing by zero throws `InvalidMoneyException`.
- `percentageOf`/`ratioOf` scales (0 to `Money::MAX_SCALE`, 100) and `roundTo` decimals (-100 to 100) outside their bounds throw `InvalidMoneyException`.
- `allocate` keeps the keys; tied leftover cents go to keys in ascending order.

### Format and serialize

```php
$price->format();          // configured locale: "$1,234.50"
$price->format('de_DE');   // "1.234,50 €"
(string) $price;           // "USD 1234.50", never locale-dependent
```

`json_encode($price)` gives `{"amount":"123450","currency":"USD","decimal":"1234.50","formatted":"$1,234.50"}` by default; every value is a string. Replace the display format app-wide with `LaravelMoney::useFormatter(new YourFormatter)` (implements `Laranex\LaravelMoney\Formatting\Formatter`).

### Store in the database

```php
protected function casts(): array
{
    return [
        'price' => Money::class,                          // BIGINT minor units, default currency
        'cost' => AsMoney::of('MMK'),                     // BIGINT minor units, always MMK
        'fee' => AsMoney::decimal('USD'),                 // DECIMAL "12.50", always USD
        'balance' => AsMoney::currencyColumn('currency'), // BIGINT, currency from the currency column
    ];
}

$product = Product::create(['price' => $price, 'fee' => $fee]);
$product->balance = $balance; // fills currency when empty, CurrencyMismatchException otherwise
$product->save();
```

- Migrations: `$table->bigInteger('price')->nullable()`, `$table->decimal('fee', 20, 4)->nullable()`, `$table->string('currency', 3)->nullable()`.
- Laravel 10 reads the `$casts` property, so use strings there: `AsMoney::class.':MMK'`, `AsMoney::class.':decimal,USD'`, `AsMoney::class.':currency_column=currency'`.
- Assign a `Money`, a moneyphp `Money\Money`, a decimal string (`'12.50'`) or `null`; ints are rejected because they are ambiguous.
- Saving Money in another currency than the cast's throws `CurrencyMismatchException`.
- `AsMoney::decimal(currencyColumn: 'currency')` is the DECIMAL version of `currencyColumn`.

### Handle errors

Every exception extends `Laranex\LaravelMoney\Exceptions\MoneyException` (an `InvalidArgumentException`); answer user input errors with HTTP 422:

```php
try {
    $price = Money::of($request->input('price'));
} catch (MoneyException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
}
```

Specific exceptions: `MoneyParseException` (bad input or too many decimals), `UnknownCurrencyException`, `CurrencyMismatchException`, `InvalidMoneyException` (floats, division by zero, invalid allocations, empty aggregates, invalid stored values, scales out of bounds, invalid config).

## Test your app

- There is nothing to fake: build values directly with `Money::of('10.50', 'USD')`.
- Set `config(['money.default_currency' => 'JPY'])` in a test to exercise another currency.
- Assert on `amount()`, `toDecimal()` or `equals()`, never on floats.

## Avoid

- Floats for amounts (`Money::of(12.5)`, `->times(1.1)`, `->percent(7.5)` throw); pass decimal strings.
- Hard-coding two decimals; JPY has none and KWD has three. Use `precision()`.
- Comparing formatted strings; formatting depends on the locale.
- Storing decimals in BIGINT columns or minor units in DECIMAL columns; pick the integer cast or `AsMoney::decimal()`.
- DECIMAL columns on SQLite (stored as floating point); use an integer cast there.
- Changing a currency's precision in `money.currencies` after amounts are stored in it.
