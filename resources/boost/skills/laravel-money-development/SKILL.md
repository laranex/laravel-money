---
name: laravel-money-development
description: >
  Store and read Eloquent money columns as moneyphp/money objects in a Laravel app with laranex/laravel-money.
license: MIT
metadata:
  author: Nay Thu Khant
---

# Laravel Money

Use this skill when a Laravel application stores prices, balances or totals and uses laranex/laravel-money.

## Primary Goal

- keep money exact: integer minor units in the database, `Money\Money` objects in PHP, never floats

## Workflow

### 1. Configure the currency

- set `MONEY_CURRENCY` (ISO 4217, default `USD`) in `.env`
- publish the config only when it must change: `php artisan vendor:publish --tag="laravel-money-config"`

### 2. Store money columns

- migrate the column as an integer in minor units: `$table->bigInteger('price')->nullable();`
- cast it: `'price' => \Laranex\LaravelMoney\Casts\MoneyCast::class`
- pin a currency per attribute when it differs from the default: `'cost' => MoneyCast::class.':USD'`

### 3. Build and read Money

- `LaravelMoney::make(1050)` builds Money from minor units in the default currency; pass a second argument for another currency
- `LaravelMoney::parse('10.50')` parses a decimal string (user input) using the currency's subunit
- `LaravelMoney::format($money)` returns a decimal string such as `"10.50"`
- the cast returns `Money|null`; JSON serialization gives `['amount' => '1050', 'currency' => 'USD']`

## Rules, References, and Templates

- no additional resource files for this skill

## Examples

- `$order->total = LaravelMoney::parse($request->string('total'));` then `$order->save();`
- `$wallet->balance = $wallet->balance->add(Money::MMK(50000));`

## Anti-patterns

- do not assign ints, floats or strings to a cast attribute; wrap them in Money first (it throws `InvalidMoneyException`)
- do not assign Money in a different currency than the attribute stores; convert it first
- do not store decimals in `decimal`/`float` columns for cast attributes; the cast stores minor units and throws `InvalidMoneyException` when it reads a decimal string such as `"10.50"`
