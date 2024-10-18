<?php

use Money\Money;

$amount = "10000";

test("parseMoney method returns Money instance", function () use ($amount) {
    expect((new \Laranex\LaravelMoney\LaravelMoney())->parseMoney($amount))
        ->toBeInstanceOf(Money::class);
});

test('parseMoney method can be access through LaravelMoneyFacade', function () use ($amount) {
    expect(\Laranex\LaravelMoney\Facades\LaravelMoney::parseMoney($amount))
        ->toBeInstanceOf(Money::class);
});
