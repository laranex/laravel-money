<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\Casts\MoneyCast;
use Laranex\LaravelMoney\Exceptions\CurrencyMismatchException;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\MoneyParseException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Tests\Fixtures\Product;
use Money\Currency;
use Money\Money as MoneyPhp;

function freshProduct(Product $product): Product
{
    return Product::query()->findOrFail($product->getKey());
}

describe('integer storage', function (): void {
    it('stores minor units and reads Money in the default currency', function (): void {
        $product = Product::query()->create(['price' => Money::of('10.50')]);

        expect($product->getAttributes()['price'])->toBe('1050');

        $fresh = freshProduct($product);

        expect($fresh->price)->toBeInstanceOf(Money::class)
            ->and($fresh->price?->amount())->toBe('1050')
            ->and($fresh->price?->currency())->toBe('USD');
    });

    it('uses fixed currencies, including zero-decimal and custom ones', function (): void {
        $product = freshProduct(Product::query()->create([
            'cost' => Money::of('2500', 'MMK'),
            'deposit' => '9.99',
            'yen' => Money::of('1500', 'JPY'),
            'points' => '300',
        ]));

        expect($product->getRawOriginal('cost'))->toBe(250000)
            ->and($product->cost?->toDecimal())->toBe('2500.00')
            ->and($product->deposit?->currency())->toBe('EUR')
            ->and($product->deposit?->amount())->toBe('999')
            ->and($product->getRawOriginal('yen'))->toBe(1500)
            ->and($product->yen?->toDecimal())->toBe('1500')
            ->and($product->points?->currency())->toBe('PTS')
            ->and($product->getRawOriginal('points'))->toBe(300);
    });

    it('follows the configured default currency', function (): void {
        config()->set('money.default_currency', 'MMK');

        $product = new Product;
        $product->price = '5000';

        expect($product->price?->currency())->toBe('MMK')
            ->and($product->getAttributes()['price'])->toBe('500000');
    });

    it('accepts decimal strings in the attribute currency, strictly', function (): void {
        $product = new Product;
        $product->price = '1,234.50';

        expect($product->price?->amount())->toBe('123450')
            ->and(fn () => $product->yen = '10.5')->toThrow(MoneyParseException::class, 'JPY allows 0 decimal places');
    });

    it('accepts moneyphp Money objects', function (): void {
        $product = new Product;
        $product->price = MoneyPhp::USD(250);

        expect($product->price?->toDecimal())->toBe('2.50');
    });

    it('keeps null as null', function (): void {
        $product = freshProduct(Product::query()->create(['price' => null, 'fee' => null, 'balance' => null]));

        expect($product->price)->toBeNull()
            ->and($product->fee)->toBeNull()
            ->and($product->balance)->toBeNull()
            ->and($product->currency)->toBeNull();

        $product->price = Money::of('1');
        $product->price = null;

        expect($product->price)->toBeNull();
    });

    it('rejects ints, floats and other values', function (mixed $value): void {
        $product = new Product;

        expect(fn () => $product->price = $value)
            ->toThrow(InvalidMoneyException::class, 'The value for [price] must be an instance of Laranex\LaravelMoney\Money or a decimal string such as "12.50"');
    })->with([
        'int' => 100,
        'float' => 1.5,
        'array' => [['amount' => 100]],
        'bool' => true,
    ]);

    it('rejects money in a different currency than the attribute', function (): void {
        $product = new Product;

        expect(fn () => $product->cost = Money::of('1', 'USD'))
            ->toThrow(CurrencyMismatchException::class, 'The attribute [cost] stores MMK amounts, but USD 1.00 was given. Convert it to MMK first.');
    });

    it('rejects stored values that are not integer minor units', function (mixed $value): void {
        expect(fn () => (new MoneyCast)->get(new Product, 'price', $value, []))
            ->toThrow(InvalidMoneyException::class, 'The stored value for [price] must be an integer amount in minor units');
    })->with(['10.50', '1e3', ' 10', '', 1.5]);

    it('reads integer and integer-string stored values', function (): void {
        $cast = new MoneyCast('EUR');

        expect($cast->get(new Product, 'price', 1050, [])?->toDecimal())->toBe('10.50')
            ->and($cast->get(new Product, 'price', '-1050', [])?->toDecimal())->toBe('-10.50')
            ->and($cast->get(new Product, 'price', '99999999999999999999', [])?->amount())->toBe('99999999999999999999');
    });

    it('round-trips arithmetic', function (): void {
        $product = Product::query()->create(['price' => Money::of('10')]);
        $product->price = $product->price?->plus('2.50')->addPercent(10);
        $product->save();

        expect(freshProduct($product)->price?->toDecimal())->toBe('13.75');
    });

    it('rejects an unknown cast currency', function (): void {
        $cast = new MoneyCast('XYZ');

        expect(fn () => $cast->get(new Product, 'price', 1, []))->toThrow(UnknownCurrencyException::class, 'Unknown currency [XYZ]');
    });
});

describe('decimal storage', function (): void {
    it('stores decimal strings and reads them back strictly', function (): void {
        $product = Product::query()->create(['fee' => Money::of('12.34'), 'kwd' => '1.005']);

        expect($product->getAttributes()['fee'])->toBe('12.34')
            ->and($product->getAttributes()['kwd'])->toBe('1.005');

        $fresh = freshProduct($product);

        expect($fresh->fee?->amount())->toBe('1234')
            ->and($fresh->fee?->currency())->toBe('USD')
            ->and($fresh->kwd?->toDecimal())->toBe('1.005')
            ->and($fresh->kwd?->currency())->toBe('KWD');
    });

    it('reads decimal strings with trailing zeros and ints', function (): void {
        $cast = AsMoney::castUsing(['decimal', 'USD']);

        expect($cast->get(new Product, 'fee', '12.3400', [])?->amount())->toBe('1234')
            ->and($cast->get(new Product, 'fee', 12, [])?->amount())->toBe('1200')
            ->and($cast->get(new Product, 'fee', '-0.50', [])?->amount())->toBe('-50')
            ->and(fn () => $cast->get(new Product, 'fee', '12.345', []))->toThrow(MoneyParseException::class)
            ->and(fn () => $cast->get(new Product, 'fee', 'abc', []))->toThrow(InvalidMoneyException::class, 'The stored value for [fee] must be a decimal amount, string "abc" given.')
            ->and(fn () => $cast->get(new Product, 'fee', [], []))->toThrow(InvalidMoneyException::class, 'array given');
    });

    it('reads exact floats from drivers that return REAL and rejects inexact ones', function (): void {
        $cast = AsMoney::castUsing(['decimal', 'USD']);

        expect($cast->get(new Product, 'fee', 12.5, [])?->toDecimal())->toBe('12.50')
            ->and(fn () => $cast->get(new Product, 'fee', 12.345, []))->toThrow(InvalidMoneyException::class, 'The database returned the float 12.345 for [fee]')
            ->and(fn () => $cast->get(new Product, 'fee', 1.0e20, []))->toThrow(InvalidMoneyException::class, 'cannot be read exactly');
    });
});

describe('currency column', function (): void {
    it('fills the currency column when it is empty', function (): void {
        $product = Product::query()->create(['balance' => Money::of('1500', 'JPY')]);

        expect($product->currency)->toBe('JPY');

        $fresh = freshProduct($product);

        expect($fresh->balance?->currency())->toBe('JPY')
            ->and($fresh->balance?->amount())->toBe('1500')
            ->and($fresh->getRawOriginal('balance'))->toBe(1500);
    });

    it('reads the currency from the column for every row', function (): void {
        $usd = Product::query()->create(['currency' => 'USD', 'balance' => '10.50']);
        $kwd = Product::query()->create(['currency' => 'KWD', 'balance' => '10.5']);

        expect(freshProduct($usd)->balance?->amount())->toBe('1050')
            ->and(freshProduct($kwd)->balance?->amount())->toBe('10500')
            ->and(freshProduct($kwd)->balance?->toDecimal())->toBe('10.500');
    });

    it('throws when the money does not match the currency column', function (): void {
        $product = new Product(['currency' => 'MMK']);

        expect(fn () => $product->balance = Money::of('1', 'USD'))
            ->toThrow(CurrencyMismatchException::class, 'The attribute [balance] reads its currency from [currency], which holds MMK, but USD 1.00 was given. Convert the amount, or change [currency] first.');
    });

    it('falls back to the default currency when the column is empty', function (): void {
        $product = new Product;
        $product->balance = '2.00';

        expect($product->currency)->toBe('USD')
            ->and($product->balance?->currency())->toBe('USD');
    });

    it('works with decimal storage', function (): void {
        $product = Product::query()->create(['total' => Money::of('3.125', 'KWD')]);

        expect($product->currency)->toBe('KWD')
            ->and($product->getAttributes()['total'])->toBe('3.125')
            ->and(freshProduct($product)->total?->toDecimal())->toBe('3.125');
    });

    it('shares one currency column between attributes', function (): void {
        $product = Product::query()->create(['currency' => 'EUR', 'balance' => '5', 'total' => '7.25']);
        $fresh = freshProduct($product);

        expect($fresh->balance?->currency())->toBe('EUR')
            ->and($fresh->total?->currency())->toBe('EUR')
            ->and($fresh->total?->toDecimal())->toBe('7.25');
    });
});

describe('cast builders', function (): void {
    it('builds cast strings', function (): void {
        expect(AsMoney::of('USD'))->toBe(AsMoney::class.':USD')
            ->and(AsMoney::decimal())->toBe(AsMoney::class.':decimal')
            ->and(AsMoney::decimal('KWD'))->toBe(AsMoney::class.':decimal,KWD')
            ->and(AsMoney::decimal(currencyColumn: 'currency'))->toBe(AsMoney::class.':decimal,currency_column=currency')
            ->and(AsMoney::currencyColumn('currency'))->toBe(AsMoney::class.':currency_column=currency')
            ->and(AsMoney::currencyColumn('currency', decimal: true))->toBe(AsMoney::class.':decimal,currency_column=currency');
    });

    it('parses cast arguments', function (): void {
        $cast = AsMoney::castUsing(['decimal', 'usd', 'currency_column=code']);

        expect($cast->storesDecimal())->toBeTrue()
            ->and($cast->currencyColumn())->toBe('code')
            ->and($cast->currency())->toEqual(new Currency('USD'))
            ->and(AsMoney::castUsing(['integer'])->storesDecimal())->toBeFalse()
            ->and(AsMoney::castUsing([])->currency()->getCode())->toBe('USD')
            ->and(Money::castUsing(['JPY'])->currency()->getCode())->toBe('JPY')
            ->and(fn () => AsMoney::castUsing(['scale=2']))->toThrow(InvalidMoneyException::class, 'Unknown money cast argument [scale=2].');
    });

    it('keeps MoneyCast working as the integer cast', function (): void {
        $cast = new MoneyCast('MMK');

        expect($cast->storesDecimal())->toBeFalse()
            ->and($cast->currencyColumn())->toBeNull()
            ->and($cast->currency()->getCode())->toBe('MMK')
            ->and((new MoneyCast(''))->currency()->getCode())->toBe('USD');
    });
});

describe('serialization', function (): void {
    it('serializes money attributes with the configured shape', function (): void {
        $product = freshProduct(Product::query()->create(['price' => Money::of('10.50'), 'cost' => null]));

        $array = $product->toArray();

        expect($array['price'])->toBe([
            'amount' => '1050',
            'currency' => 'USD',
            'decimal' => '10.50',
            'formatted' => Money::of('10.50')->format(),
        ])->and($array['cost'])->toBeNull()
            ->and(json_decode($product->toJson(), true)['price']['amount'])->toBe('1050');
    });
});
