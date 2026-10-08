<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\Casts\MoneyCast;
use Laranex\LaravelMoney\Money;

/**
 * @property Money|null $price
 * @property Money|null $cost
 * @property Money|null $deposit
 * @property Money|null $points
 * @property Money|null $yen
 * @property Money|null $fee
 * @property Money|null $kwd
 * @property string|null $currency
 * @property Money|null $balance
 * @property Money|null $total
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => Money::class,
        'cost' => MoneyCast::class.':MMK',
        'deposit' => AsMoney::class.':EUR',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->mergeCasts([
            'points' => AsMoney::of('PTS'),
            'yen' => AsMoney::of('JPY'),
            'fee' => AsMoney::decimal('USD'),
            'kwd' => AsMoney::decimal('KWD'),
            'balance' => AsMoney::currencyColumn('currency'),
            'total' => AsMoney::decimal(currencyColumn: 'currency'),
        ]);

        parent::__construct($attributes);
    }
}
