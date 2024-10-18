<?php

use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyInstanceException;
use Laranex\LaravelMoney\Facades\LaravelMoney as LaravelMoneyFacade;
use Laranex\LaravelMoney\Traits\HasMoneyFields;
use Money\Money;

class Wallet extends Model
{
    use HasMoneyFields;

    protected $moneyFields = ['balance'];
}

$wallet = new Wallet;
$amount = '10000';

test('convert the Money instance into string through mutator', function () use ($wallet, $amount) {
    $wallet->balance = LaravelMoneyFacade::parseMoney($amount);

    expect($wallet->getAttributes()['balance'])
        ->toBe($amount);
});

test('throw exception when setting invalid Money instance', function () use ($wallet, $amount) {
    expect(fn () => $wallet->balance = $amount)
        ->toThrow(InvalidMoneyInstanceException::class);
});

test('convert string to Money instance through accessor', function () use ($wallet, $amount) {
    $wallet->balance = LaravelMoneyFacade::parseMoney($amount);

    expect($wallet->balance)
        ->toBeInstanceOf(Money::class);
});
