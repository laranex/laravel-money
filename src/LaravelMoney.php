<?php

namespace Laranex\LaravelMoney;

use Money\Currency;
use Money\Money;

class LaravelMoney
{
    public function parseMoney(string $amount): Money
    {
        return new Money(
            $amount,
            new Currency('USD')
        );
    }
}
