# Laravel Money

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-money.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-money)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranex/laravel-money/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranex/laravel-money/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-money.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-money)
[![License](https://img.shields.io/packagist/l/laranex/laravel-money.svg?style=flat-square)](LICENSE.md)

Laravel Money casts Eloquent money columns to [`moneyphp/money`](https://www.moneyphp.org/) objects, so your models hand you exact `Money` values instead of floats. Amounts are stored as integers in minor units (cents, pya), the default currency comes from config, and each attribute can pin its own currency. It is for Laravel applications that store prices, balances or totals and want exact arithmetic without rounding errors.

## Documentation

Full documentation lives at **[laranex.vercel.app/laravel-money](https://laranex.vercel.app/laravel-money)**.

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require laranex/laravel-money
```

Set the default currency in `.env` (ISO 4217 code, defaults to `USD`):

```dotenv
MONEY_CURRENCY=MMK
```

Publish the config file only if you want to edit it:

```bash
php artisan vendor:publish --tag="laravel-money-config"
```

## Usage

Store amounts in an integer column (`$table->bigInteger('price')`) and cast it:

```php
use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Casts\MoneyCast;

class Product extends Model
{
    protected $casts = [
        'price' => MoneyCast::class,          // default currency (MONEY_CURRENCY)
        'cost' => MoneyCast::class.':USD',    // always USD
    ];
}
```

```php
use Laranex\LaravelMoney\Facades\LaravelMoney;
use Money\Money;

$product->price = LaravelMoney::parse('1500.50');  // decimal string in the default currency
$product->cost = Money::USD(1050);                 // minor units: 10.50 USD
$product->save();

$product->price->add(LaravelMoney::make(100));     // Money in, Money out
LaravelMoney::format($product->cost);              // "10.50"
$product->toArray()['cost'];                       // ['amount' => '1050', 'currency' => 'USD']
```

`null` columns stay `null`. Assigning anything other than a `Money` object, or a `Money` in a different currency than the attribute stores, throws `Laranex\LaravelMoney\Exceptions\InvalidMoneyException`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Nay Thu Khant](https://github.com/NayThuKhant)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
