<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

class LaravelMoney
{
    /**
     * @param  non-empty-string  $defaultCurrency
     */
    public function __construct(
        private readonly string $defaultCurrency,
    ) {}

    /**
     * The currency used when none is given explicitly.
     */
    public function defaultCurrency(): Currency
    {
        return new Currency($this->defaultCurrency);
    }

    /**
     * Resolve a currency, falling back to the default currency.
     */
    public function currency(Currency|string|null $currency = null): Currency
    {
        if ($currency instanceof Currency) {
            return $currency;
        }

        if ($currency === null || $currency === '') {
            return $this->defaultCurrency();
        }

        return new Currency($currency);
    }

    /**
     * Create a Money object from an amount expressed in minor units (cents).
     *
     * @param  int|numeric-string  $amount
     */
    public function make(int|string $amount, Currency|string|null $currency = null): Money
    {
        return new Money($amount, $this->currency($currency));
    }

    /**
     * Create a Money object from a decimal string such as "10.50".
     */
    public function parse(string $decimal, Currency|string|null $currency = null): Money
    {
        return (new DecimalMoneyParser(new ISOCurrencies))->parse($decimal, $this->currency($currency));
    }

    /**
     * Format a Money object as a decimal string such as "10.50".
     */
    public function format(Money $money): string
    {
        return (new DecimalMoneyFormatter(new ISOCurrencies))->format($money);
    }
}
