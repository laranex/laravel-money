<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;

it('runs the introduction example', function (): void {
    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->bigInteger('subtotal');
        $table->string('currency', 3)->nullable();
        $table->bigInteger('total');
    });

    $order = new class extends Model
    {
        protected $table = 'orders';

        public $timestamps = false;

        protected $guarded = [];

        protected $casts = [
            'subtotal' => Money::class,
            'total' => AsMoney::class.':currency_column=currency',
        ];
    };

    $subtotal = Money::of('1,234.50', 'USD');
    $total = $subtotal->addPercent('8.875');
    [$a, $b, $c] = Money::of('100')->split(3);

    expect($total->toDecimal())->toBe('1344.06')
        ->and([$a->toDecimal(), $b->toDecimal(), $c->toDecimal()])->toBe(['33.34', '33.33', '33.33'])
        ->and(Money::of('25')->percentageOf(Money::of('200')))->toBe('12.50')
        ->and(Money::of('1500', 'JPY')->times('1.1')->toDecimal())->toBe('1650')
        ->and(money('1234.50', 'USD')->equals($subtotal))->toBeTrue();

    $order->fill(['subtotal' => $subtotal, 'total' => $total])->save();
    $order = $order->newQuery()->findOrFail($order->getKey());

    expect($order->getAttribute('currency'))->toBe('USD')
        ->and($order->toArray()['total'])->toBe([
            'amount' => '134406',
            'currency' => 'USD',
            'decimal' => '1344.06',
            'formatted' => extension_loaded('intl') ? '$1,344.06' : 'USD 1344.06',
        ]);
});

it('runs the documentation examples', function (): void {
    $price = Money::of('1,234.50', 'USD');

    expect($price->addPercent('8.875')->toDecimal())->toBe('1344.06')
        ->and($price->subtractPercent(15)->toDecimal())->toBe('1049.32')
        ->and(Money::of('200')->percent('7.5')->toDecimal())->toBe('15.00')
        ->and(Money::of('200')->addPercent(7)->toDecimal())->toBe('214.00')
        ->and(Money::of('200')->subtractPercent(15)->toDecimal())->toBe('170.00')
        ->and(Money::of('50')->ratioOf(Money::of('200')))->toBe('0.2500')
        ->and(Money::of('12.500', 'USD')->toDecimal())->toBe('12.50')
        ->and(Money::of('1.235', 'USD', Rounding::HalfUp)->toDecimal())->toBe('1.24');
});
