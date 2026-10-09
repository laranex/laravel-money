<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\CurrencyRegistry;
use Laranex\LaravelMoney\Formatting\Formatter;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currencies;

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
        ->and(Money::of('25')->percentageOf(Money::of('200'), 2))->toBe('12.50')
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
        ->and(Money::of('50')->ratioOf(Money::of('200'), 4))->toBe('0.2500')
        ->and(Money::of('12.500', 'USD')->toDecimal())->toBe('12.50')
        ->and(Money::of('1.235', 'USD', Rounding::HalfUp)->toDecimal())->toBe('1.24');
});

it('runs the facade, registry and formatter examples', function (): void {
    config()->set('money.currencies', ['PTS' => 0]);
    app()->forgetInstance(CurrencyRegistry::class);
    app()->forgetInstance(LaravelMoney::class);

    $registry = Laranex\LaravelMoney\Facades\LaravelMoney::currencies();

    expect(Laranex\LaravelMoney\Facades\LaravelMoney::currency('mmk')->getCode())->toBe('MMK')
        ->and(Laranex\LaravelMoney\Facades\LaravelMoney::serialization())->toBe(['amount' => 'minor', 'include_decimal' => true, 'include_formatted' => true])
        ->and($registry->has('PTS'))->toBeTrue()
        ->and($registry->resolve('jpy')->getCode())->toBe('JPY')
        ->and($registry->precision('JPY'))->toBe(0)
        ->and($registry->custom())->toBe(['PTS' => 0])
        ->and($registry->currencies())->toBeInstanceOf(Currencies::class);

    $formatter = new class implements Formatter
    {
        public function format(string $decimal, string $currency, int $precision, string $locale): string
        {
            return $currency.' '.$decimal;
        }
    };

    Laranex\LaravelMoney\Facades\LaravelMoney::useFormatter($formatter);

    expect(Money::of('-1234.5')->format())->toBe('USD -1234.50')
        ->and(Laranex\LaravelMoney\Facades\LaravelMoney::formatter())->toBe($formatter);

    Laranex\LaravelMoney\Facades\LaravelMoney::useFormatter(null);

    expect(Laranex\LaravelMoney\Facades\LaravelMoney::formatter())->not->toBe($formatter);

    $cast = AsMoney::castUsing(['decimal', 'currency_column=currency']);

    expect($cast->storesDecimal())->toBeTrue()
        ->and($cast->currencyColumn())->toBe('currency')
        ->and($cast->currency(['currency' => 'jpy'])->getCode())->toBe('JPY')
        ->and($cast->currency()->getCode())->toBe('USD');
});
