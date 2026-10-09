# Changelog

All notable changes to `laravel-money` will be documented in this file.

## v4.0.0 - Unreleased

The first stable release: a currency-aware money toolkit for Laravel.

### Added
- `Laranex\LaravelMoney\Money`: an immutable value object around `moneyphp/money` (Castable, Arrayable, JsonSerializable, Stringable, Macroable). Precision always comes from the currency registry (ISO 4217: USD 2, JPY 0, KWD 3, MMK 2...), never from hard-coded rules.
  - Build: `Money::of('1,234.50', 'USD')` (strict: extra decimals throw unless a `Rounding` is passed), `Money::ofMinor(123450)`, `Money::zero()`, `Money::fromMoneyPhp()` / `toMoneyPhp()`, and the `money('12.34', 'USD')` helper.
  - Read: `amount()` (minor units), `toDecimal()`, `currency()`, `precision()`, `isZero()`, `isPositive()`, `isNegative()`, `equals()`, `compare()`, `greaterThan()`, `greaterThanOrEqual()`, `lessThan()`, `lessThanOrEqual()`, `isSameCurrency()`.
  - Calculate: `plus()`, `minus()`, `times()`, `dividedBy()`, `mod()`, `negated()`, `absolute()`, `roundTo()`, and `Money::sum()`, `min()`, `max()`, `avg()` over any iterable. Int and string operands are decimal amounts in the same currency.
  - Percentages: `percent()`, `addPercent()`, `subtractPercent()`, `percentageOf()` and `ratioOf()` (decimal strings with a non-negative scale).
  - Allocation without losing a minor unit: `split()` and `allocate()` (keys preserved; 100.00 split 3 ways is 33.34, 33.33, 33.33).
  - Formatting: `format(?string $locale)` and `__toString()`, replaceable app-wide with `LaravelMoney::useFormatter()` and the `Formatter` interface. With ext-intl the locale's symbols, digits and grouping are applied to the exact decimal string, so huge amounts never pass through a float; without intl it prints `USD 1234.50`.
  - Serialization: `{"amount": "123450", "currency": "USD", "decimal": "1234.50", "formatted": "$1,234.50"}`, configurable with `money.serialization`. Amounts are always strings.
- `Laranex\LaravelMoney\Rounding` enum (`HalfUp` default, `HalfDown`, `HalfEven`, `HalfOdd`, `HalfPositiveInfinity`, `HalfNegativeInfinity`, `Ceiling`, `Floor`) with `toMoneyPhp()` / `fromMoneyPhp()`. All arithmetic is exact (bcmath on integers); floats are rejected everywhere with a hint.
- `CurrencyRegistry` singleton: ISO 4217 plus custom currencies from `money.currencies` (e.g. `['PTS' => 0]`), validating every currency the package touches.
- Eloquent casts: `'price' => Money::class`, `AsMoney::of('USD')` (or `AsMoney::class.':USD'` on Laravel 10), `AsMoney::currencyColumn('currency')` (fills an empty currency column, throws on a mismatch), and `AsMoney::decimal()` for DECIMAL columns. Casts accept `Money`, moneyphp `Money` or decimal strings; `null` stays `null`.
- Exceptions under `Laranex\LaravelMoney\Exceptions\MoneyException` (an `InvalidArgumentException`): `InvalidMoneyException`, `CurrencyMismatchException`, `UnknownCurrencyException`, `MoneyParseException`.
- `config/money.php`: `default_currency` (env `MONEY_CURRENCY`), `rounding`, `currencies`, `locale`, `serialization`.
- An agent skill for Laravel Boost (`resources/boost/skills`) and `npx skills add laranex/laravel-money` (`skills/`).

### Changed
- Requires PHP 8.1+ and supports Laravel 10 through 13.
- Rebuilt on the official Laravel package skeleton (Pest, PHPStan, Pint, Testbench workbench, GitHub Actions matrix).
- Requires `moneyphp/money` ^4.0 and ext-bcmath (moneyphp 3 multiplied and divided through floats). ext-intl is suggested for formatting.
- Casts and the `LaravelMoney` facade return `Laranex\LaravelMoney\Money` instead of `Money\Money`. `MoneyCast` remains as the integer cast (`MoneyCast::class.':MMK'` still works).
- The facade offers `of()`, `ofMinor()`, `zero()`, `fromMoneyPhp()`, `currency()`, `defaultCurrency()`, `precision()`, `rounding()`, `locale()`, `format()` and `currencies()`; `make()`, `parse()` and `parseMoney()` are removed.
- Dropped `spatie/laravel-package-tools`, the `HasMoneyFields` trait and `InvalidMoneyInstanceException`.

### Upgrading
- Replace `use HasMoneyFields;` and `protected $moneyFields = ['balance'];` with `protected $casts = ['balance' => Money::class];`.
- Replace `LaravelMoney::parseMoney($minor)` / `make($minor)` with `Money::ofMinor($minor)`, and `parse('10.50')` with `Money::of('10.50')`.
- Replace moneyphp calls on cast values: `add()` → `plus()`, `subtract()` → `minus()`, `multiply()` → `times()`, `divide()` → `dividedBy()`, `allocateTo()` → `split()`, `getAmount()` → `amount()`; or call `toMoneyPhp()`.
- Model JSON now includes `decimal` and `formatted`; set `money.serialization` to `['amount' => 'minor', 'include_decimal' => false, 'include_formatted' => false]` for the old `{amount, currency}` shape.
- Catch `MoneyException` (or the specific subclasses) instead of `InvalidMoneyInstanceException`.
