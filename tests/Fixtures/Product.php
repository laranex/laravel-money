<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Casts\MoneyCast;
use Money\Money;

/**
 * @property Money|null $price
 * @property Money|null $cost
 * @property Money|null $deposit
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => MoneyCast::class,
        'cost' => MoneyCast::class.':MMK',
        'deposit' => MoneyCast::class.':EUR',
    ];
}
