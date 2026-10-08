<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Casts\MoneyCast;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\Tests\Fixtures\Product;
use Money\Currency;
use Money\Money;

it('stores a Money object as its amount in minor units', function () {
    $product = new Product;
    $product->price = Money::USD(1050);

    expect($product->getAttributes()['price'])->toBe('1050');
});

it('returns a Money object in the default currency', function () {
    $product = Product::query()->create(['price' => Money::USD(1050)]);

    $fresh = Product::query()->findOrFail($product->getKey());

    expect($fresh->price)->toBeInstanceOf(Money::class)
        ->and($fresh->price->getAmount())->toBe('1050')
        ->and($fresh->price->getCurrency()->getCode())->toBe('USD')
        ->and($fresh->price->equals(Money::USD(1050)))->toBeTrue();
});

it('uses the per-attribute currency passed as a cast argument', function () {
    $product = Product::query()->create([
        'cost' => Money::MMK(250000),
        'deposit' => Money::EUR(999),
    ]);

    $fresh = Product::query()->findOrFail($product->getKey());

    expect($fresh->cost?->getCurrency()->getCode())->toBe('MMK')
        ->and($fresh->cost?->getAmount())->toBe('250000')
        ->and($fresh->deposit?->getCurrency()->getCode())->toBe('EUR')
        ->and($fresh->deposit?->getAmount())->toBe('999');
});

it('follows the configured default currency', function () {
    config()->set('money.default_currency', 'MMK');
    $this->app->forgetInstance(LaravelMoney::class);

    $product = new Product;
    $product->price = Money::MMK(5000);

    expect($product->price?->getCurrency()->getCode())->toBe('MMK');
});

it('returns null for a null column and stores null', function () {
    $product = Product::query()->create(['price' => null]);

    $fresh = Product::query()->findOrFail($product->getKey());

    expect($fresh->price)->toBeNull()
        ->and($fresh->getAttributes()['price'])->toBeNull();
});

it('accepts a Money object set to null again', function () {
    $product = new Product;
    $product->price = Money::USD(100);
    $product->price = null;

    expect($product->price)->toBeNull();
});

it('rejects values that are not Money objects', function (mixed $value) {
    $product = new Product;

    expect(fn () => $product->price = $value)
        ->toThrow(InvalidMoneyException::class, 'The value for [price] must be an instance of Money\Money');
})->with([
    'integer' => 100,
    'string' => '100',
    'float' => 1.5,
    'array' => [['amount' => 100]],
]);

it('rejects a Money object in a different currency than the cast', function () {
    $product = new Product;

    expect(fn () => $product->cost = Money::USD(100))
        ->toThrow(InvalidMoneyException::class, 'The attribute [cost] stores MMK amounts, USD given.');
});

it('rejects a stored value that is not an integer amount', function () {
    $cast = new MoneyCast;

    expect(fn () => $cast->get(new Product, 'price', 1.5, []))
        ->toThrow(InvalidMoneyException::class, 'The stored value for [price] must be an integer amount in minor units, float given.');
});

it('casts integer and numeric string stored values', function () {
    $cast = new MoneyCast('EUR');
    $product = new Product;

    expect($cast->get($product, 'price', 1050, [])?->getAmount())->toBe('1050')
        ->and($cast->get($product, 'price', '1050', [])?->getAmount())->toBe('1050')
        ->and($cast->get($product, 'price', 1050, [])?->getCurrency()->getCode())->toBe('EUR');
});

it('serializes to the moneyphp amount and currency shape', function () {
    $product = Product::query()->create(['price' => Money::USD(1050), 'cost' => null]);

    $array = Product::query()->findOrFail($product->getKey())->toArray();

    expect($array['price'])->toBe(['amount' => '1050', 'currency' => 'USD'])
        ->and($array['cost'])->toBeNull();
});

it('exposes the currency the cast resolves to', function () {
    expect((new MoneyCast)->currency())->toEqual(new Currency('USD'))
        ->and((new MoneyCast('MMK'))->currency())->toEqual(new Currency('MMK'));
});

it('round-trips arithmetic on the cast value', function () {
    $product = Product::query()->create(['price' => Money::USD(1000)]);
    $product->price = $product->price?->add(Money::USD(250));
    $product->save();

    expect(Product::query()->findOrFail($product->getKey())->price?->getAmount())->toBe('1250');
});
