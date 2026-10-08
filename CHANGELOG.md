# Changelog

All notable changes to `laravel-money` will be documented in this file.

## v4.0.0 - Unreleased

First release.

### Changed
- Requires PHP 8.1+ and supports Laravel 10 through 13.
- Rebuilt on the official Laravel package skeleton (Pest, PHPStan, Pint, Testbench workbench, GitHub Actions matrix).
- Built on `moneyphp/money` ^4 (the unreleased code depended on the unrelated `money/money` package).
- The currency is no longer hard-coded to USD: `config/money.php` adds `default_currency` (env `MONEY_CURRENCY`, default `USD`), and each attribute can pin its own currency with `MoneyCast::class.':MMK'`.
- Money attributes use a standard Eloquent cast, `Laranex\LaravelMoney\Casts\MoneyCast`, instead of the `HasMoneyFields` trait and `$moneyFields` property. `null` values are supported and models serialize money as `['amount' => '1050', 'currency' => 'USD']`.
- The `LaravelMoney` facade offers `make()`, `parse()`, `format()`, `currency()` and `defaultCurrency()`; `parseMoney()` is removed.
- Invalid assignments throw `Laranex\LaravelMoney\Exceptions\InvalidMoneyException` (an `InvalidArgumentException`), replacing `InvalidMoneyInstanceException`; assigning a `Money` in a different currency than the attribute stores is now rejected.
- Dropped `spatie/laravel-package-tools`.

### Upgrading
- Replace `use HasMoneyFields;` and `protected $moneyFields = ['balance'];` with `protected $casts = ['balance' => MoneyCast::class];`.
- Replace `LaravelMoney::parseMoney($minorUnits)` with `LaravelMoney::make($minorUnits)`, or `LaravelMoney::parse('10.50')` for decimal strings.
- Set `MONEY_CURRENCY` if your amounts are not USD, and catch `InvalidMoneyException` instead of `InvalidMoneyInstanceException`.
