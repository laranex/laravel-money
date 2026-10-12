<?php

use Laranex\LaravelMoney\LaravelMoney;
use Money\Money;

$amount = 10000;

test('parseMoney method returns Money instance', function () use ($amount) {
    expect((new LaravelMoney)->parseMoney($amount))
        ->toBeInstanceOf(Money::class);
});

test('parseMoney method can be accessed through LaravelMoneyFacade', function () use ($amount) {
    expect(Laranex\LaravelMoney\Facades\LaravelMoney::parseMoney($amount))
        ->toBeInstanceOf(Money::class);
});
