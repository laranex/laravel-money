<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Exceptions\CurrencyMismatchException;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\MoneyException;
use Laranex\LaravelMoney\Exceptions\MoneyParseException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;
use Money\Currency;
use Money\Money as MoneyPhp;

describe('construction', function (): void {
    it('uses each currency\'s own precision', function (string $currency, int $precision, string $input, string $minor, string $decimal): void {
        $money = Money::of($input, $currency);

        expect($money->precision())->toBe($precision)
            ->and($money->amount())->toBe($minor)
            ->and($money->toDecimal())->toBe($decimal)
            ->and($money->currency())->toBe($currency);
    })->with([
        'USD (2)' => ['USD', 2, '1234.5', '123450', '1234.50'],
        'MMK (2)' => ['MMK', 2, '1500', '150000', '1500.00'],
        'JPY (0)' => ['JPY', 0, '2500', '2500', '2500'],
        'KWD (3)' => ['KWD', 3, '1.25', '1250', '1.250'],
        'custom PTS (0)' => ['PTS', 0, '300', '300', '300'],
    ]);

    it('accepts ints as whole decimal amounts', function (): void {
        expect(Money::of(12)->amount())->toBe('1200')
            ->and(Money::of(12, 'JPY')->amount())->toBe('12')
            ->and(Money::of(-3, 'KWD')->toDecimal())->toBe('-3.000');
    });

    it('defaults to the configured currency', function (): void {
        expect(Money::of('1')->currency())->toBe('USD');

        config()->set('money.default_currency', 'mmk');

        expect(Money::of('1')->currency())->toBe('MMK')
            ->and(Money::zero()->currency())->toBe('MMK');
    });

    it('accepts currency codes in any case and moneyphp Currency objects', function (): void {
        expect(Money::of('1', 'eur')->currency())->toBe('EUR')
            ->and(Money::of('1', new Currency('JPY'))->currency())->toBe('JPY');
    });

    it('strips grouping commas and spaces', function (string $input, string $minor): void {
        expect(Money::of($input, 'USD')->amount())->toBe($minor);
    })->with([
        ['1,234.50', '123450'],
        ['1,234,567.89', '123456789'],
        ['12,34,567.00', '123456700'],
        ['1 234 567.01', '123456701'],
        ["1\u{00A0}234.50", '123450'],
        ['  99.99  ', '9999'],
        ['+5', '500'],
    ]);

    it('parses negatives and normalizes negative zero', function (): void {
        expect(Money::of('-1,234.56')->amount())->toBe('-123456')
            ->and(Money::of('-0.00')->amount())->toBe('0')
            ->and(Money::of('-0')->isNegative())->toBeFalse()
            ->and(Money::of('007.10')->toDecimal())->toBe('7.10');
    });

    it('goes beyond 64-bit integers', function (): void {
        $money = Money::ofMinor(PHP_INT_MAX);

        expect($money->plus('0.01')->amount())->toBe('9223372036854775808')
            ->and($money->negated()->minus('0.02')->amount())->toBe('-9223372036854775809')
            ->and($money->times(2)->amount())->toBe('18446744073709551614')
            ->and(Money::ofMinor('-99999999999999999999999', 'KWD')->dividedBy(7)->amount())->toBe('-14285714285714285714286');
    });

    it('handles very large amounts as strings without losing digits', function (): void {
        $money = Money::of('123456789012345678901234567890.12', 'USD');

        expect($money->amount())->toBe('12345678901234567890123456789012')
            ->and($money->toDecimal())->toBe('123456789012345678901234567890.12')
            ->and($money->plus('0.01')->toDecimal())->toBe('123456789012345678901234567890.13')
            ->and($money->times('2')->toDecimal())->toBe('246913578024691357802469135780.24');
    });

    it('is strict about decimals the currency does not have', function (string $input, string $currency, string $message): void {
        expect(fn () => Money::of($input, $currency))->toThrow(MoneyParseException::class, $message);
    })->with([
        ['1.234', 'USD', 'USD allows 2 decimal places, but "1.234" has 3.'],
        ['1.5', 'JPY', 'JPY allows 0 decimal places, but "1.5" has 1.'],
        ['0.0001', 'KWD', 'KWD allows 3 decimal places, but "0.0001" has 4.'],
        ['10.1', 'PTS', 'PTS allows 0 decimal places'],
    ]);

    it('accepts extra decimals that are zeros', function (): void {
        expect(Money::of('12.5000', 'USD')->amount())->toBe('1250')
            ->and(Money::of('100.0', 'JPY')->amount())->toBe('100');
    });

    it('rounds extra decimals when a rounding mode is given', function (): void {
        expect(Money::of('1.235', 'USD', Rounding::HalfUp)->amount())->toBe('124')
            ->and(Money::of('1.235', 'USD', Rounding::HalfEven)->amount())->toBe('124')
            ->and(Money::of('1.225', 'USD', Rounding::HalfEven)->amount())->toBe('122')
            ->and(Money::of('1.239', 'USD', Rounding::Floor)->amount())->toBe('123')
            ->and(Money::of('-1.231', 'USD', Rounding::Floor)->amount())->toBe('-124')
            ->and(Money::of('2.5', 'JPY', Rounding::HalfDown)->amount())->toBe('2')
            ->and(Money::of('2.5', 'JPY', Rounding::HalfUp)->amount())->toBe('3');
    });

    it('rejects malformed amounts', function (string $input): void {
        expect(fn () => Money::of($input))->toThrow(MoneyParseException::class, 'Cannot parse "'.$input.'" as an amount.');
    })->with(['', 'abc', '1.2.3', '12,50', '1,23', '.5', '5.', '1e3', '1,234.5,0', '--1', '$10', '१२३', '၁၂၃', '၁,၂၃၄.၅၀', '١٢٣', '１２']);

    it('rejects floats with a hint', function (): void {
        expect(fn () => Money::of(12.5))->toThrow(InvalidMoneyException::class, 'Floats are not accepted for money (12.5 given) because they cannot hold decimal amounts exactly. Pass a string such as "12.5", or an integer.')
            ->and(fn () => Money::ofMinor(1.0))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
            ->and(fn () => Money::of('1')->plus(0.1))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
            ->and(fn () => Money::of('1')->times(1.5))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
            ->and(fn () => Money::of('1')->percent(7.5))->toThrow(InvalidMoneyException::class, 'Floats are not accepted')
            ->and(fn () => Money::of('1')->allocate([0.5, 0.5]))->toThrow(InvalidMoneyException::class, 'Floats are not accepted');
    });

    it('builds from minor units', function (): void {
        expect(Money::ofMinor(123450)->toDecimal())->toBe('1234.50')
            ->and(Money::ofMinor('-5', 'KWD')->toDecimal())->toBe('-0.005')
            ->and(Money::ofMinor('00042', 'JPY')->amount())->toBe('42')
            ->and(Money::ofMinor('99999999999999999999999')->amount())->toBe('99999999999999999999999')
            ->and(fn () => Money::ofMinor('10.50'))->toThrow(InvalidMoneyException::class, 'Minor-unit amounts must be integers such as 1050 or "1050", "10.50" given.');
    });

    it('builds zero', function (): void {
        expect(Money::zero('KWD')->toDecimal())->toBe('0.000')
            ->and(Money::zero()->isZero())->toBeTrue();
    });

    it('rejects unknown currencies', function (): void {
        expect(fn () => Money::of('1', 'XYZ'))->toThrow(UnknownCurrencyException::class, 'Unknown currency [XYZ].')
            ->and(fn () => Money::ofMinor(1, ' '))->toThrow(UnknownCurrencyException::class);
    });

    it('rejects an unknown default currency', function (): void {
        config()->set('money.default_currency', 'NOPE');

        expect(fn () => Money::zero())->toThrow(UnknownCurrencyException::class, 'The default currency [NOPE]');
    });

    it('converts to and from moneyphp', function (): void {
        $money = Money::fromMoneyPhp(MoneyPhp::KWD(1250));

        expect($money->toDecimal())->toBe('1.250')
            ->and($money->toMoneyPhp())->toEqual(MoneyPhp::KWD(1250))
            ->and(fn () => Money::fromMoneyPhp(new MoneyPhp(1, new Currency('XYZ'))))->toThrow(UnknownCurrencyException::class);
    });

    it('roots every exception in MoneyException', function (): void {
        foreach ([InvalidMoneyException::class, CurrencyMismatchException::class, UnknownCurrencyException::class, MoneyParseException::class] as $class) {
            expect(is_subclass_of($class, MoneyException::class))->toBeTrue();
        }

        expect(is_subclass_of(MoneyException::class, InvalidArgumentException::class))->toBeTrue();
    });
});

describe('reading and comparing', function (): void {
    it('reports its sign', function (): void {
        expect(Money::of('0')->isZero())->toBeTrue()
            ->and(Money::of('0.01')->isPositive())->toBeTrue()
            ->and(Money::of('-0.01')->isNegative())->toBeTrue()
            ->and(Money::of('-0.01')->isPositive())->toBeFalse();
    });

    it('compares amounts in the same currency', function (): void {
        $ten = Money::of('10');

        expect($ten->equals(Money::of('10.00')))->toBeTrue()
            ->and($ten->equals('10'))->toBeTrue()
            ->and($ten->equals(Money::of('10', 'EUR')))->toBeFalse()
            ->and($ten->compare('9.99'))->toBe(1)
            ->and($ten->compare('10'))->toBe(0)
            ->and($ten->compare(Money::of('11')))->toBe(-1)
            ->and($ten->greaterThan('9.99'))->toBeTrue()
            ->and($ten->greaterThanOrEqual('10'))->toBeTrue()
            ->and($ten->lessThan(11))->toBeTrue()
            ->and($ten->lessThanOrEqual('10.00'))->toBeTrue()
            ->and($ten->lessThan('10'))->toBeFalse();
    });

    it('checks currencies', function (): void {
        expect(Money::of('1')->isSameCurrency(Money::of('2'), Money::of('3')))->toBeTrue()
            ->and(Money::of('1')->isSameCurrency(Money::of('2'), Money::of('3', 'EUR')))->toBeFalse();
    });

    it('refuses to compare different currencies', function (): void {
        expect(fn () => Money::of('10')->greaterThan(Money::of('5', 'EUR')))
            ->toThrow(CurrencyMismatchException::class, 'Cannot combine USD 10.00 with EUR 5.00: the amounts are in different currencies. Convert one of them first');
    });
});

describe('arithmetic', function (): void {
    it('adds and subtracts money and decimal strings', function (): void {
        $price = Money::of('19.99');

        expect($price->plus(Money::of('5.01'))->toDecimal())->toBe('25.00')
            ->and($price->plus('0.01', 1, Money::of('0.10'))->toDecimal())->toBe('21.10')
            ->and($price->minus('20')->toDecimal())->toBe('-0.01')
            ->and($price->minus(Money::of('9.99'), '10')->isZero())->toBeTrue()
            ->and($price->toDecimal())->toBe('19.99');
    });

    it('is strict about decimal operands', function (): void {
        expect(fn () => Money::of('1', 'JPY')->plus('0.5'))->toThrow(MoneyParseException::class, 'JPY allows 0 decimal places');
    });

    it('refuses to add different currencies', function (): void {
        expect(fn () => Money::of('1')->plus(Money::of('1', 'MMK')))->toThrow(CurrencyMismatchException::class, 'Cannot combine USD 1.00 with MMK 1.00')
            ->and(fn () => Money::of('1')->minus(Money::of('1', 'JPY')))->toThrow(CurrencyMismatchException::class);
    });

    it('multiplies with rounding', function (): void {
        expect(Money::of('10.00')->times(3)->toDecimal())->toBe('30.00')
            ->and(Money::of('10.00')->times('1.5')->toDecimal())->toBe('15.00')
            ->and(Money::of('0.05')->times('0.5')->toDecimal())->toBe('0.03')
            ->and(Money::of('0.05')->times('0.5', Rounding::HalfDown)->toDecimal())->toBe('0.02')
            ->and(Money::of('0.05')->times('0.5', Rounding::Floor)->toDecimal())->toBe('0.02')
            ->and(Money::of('-0.05')->times('0.5')->toDecimal())->toBe('-0.03')
            ->and(Money::of('100', 'JPY')->times('-0.333')->amount())->toBe('-33');
    });

    it('divides with rounding', function (): void {
        expect(Money::of('10.00')->dividedBy(3)->toDecimal())->toBe('3.33')
            ->and(Money::of('20.00')->dividedBy(3)->toDecimal())->toBe('6.67')
            ->and(Money::of('20.00')->dividedBy(3, Rounding::Floor)->toDecimal())->toBe('6.66')
            ->and(Money::of('10.00')->dividedBy('0.5')->toDecimal())->toBe('20.00')
            ->and(Money::of('10.00')->dividedBy('-4')->toDecimal())->toBe('-2.50')
            ->and(Money::of('1', 'KWD')->dividedBy(8)->toDecimal())->toBe('0.125')
            ->and(fn () => Money::of('1')->dividedBy(0))->toThrow(InvalidMoneyException::class, 'Cannot divide money by zero.')
            ->and(fn () => Money::of('1')->dividedBy('0.00'))->toThrow(InvalidMoneyException::class, 'Cannot divide money by zero.');
    });

    it('takes the remainder', function (): void {
        expect(Money::of('10.00')->mod('3')->toDecimal())->toBe('1.00')
            ->and(Money::of('10.00')->mod(Money::of('0.30'))->toDecimal())->toBe('0.10')
            ->and(fn () => Money::of('10')->mod('0'))->toThrow(InvalidMoneyException::class, 'Cannot divide money by zero.');
    });

    it('negates and takes the absolute value', function (): void {
        expect(Money::of('5')->negated()->toDecimal())->toBe('-5.00')
            ->and(Money::of('-5')->negated()->toDecimal())->toBe('5.00')
            ->and(Money::of('0')->negated()->amount())->toBe('0')
            ->and(Money::of('-5')->absolute()->toDecimal())->toBe('5.00')
            ->and(Money::of('5')->absolute()->toDecimal())->toBe('5.00');
    });

    it('aggregates collections of money', function (): void {
        $amounts = collect([Money::of('10'), Money::of('2.50'), Money::of('7.51')]);

        expect(Money::sum($amounts)->toDecimal())->toBe('20.01')
            ->and(Money::sum([Money::of('1')])->toDecimal())->toBe('1.00')
            ->and(Money::min($amounts)->toDecimal())->toBe('2.50')
            ->and(Money::max($amounts)->toDecimal())->toBe('10.00')
            ->and(Money::avg($amounts)->toDecimal())->toBe('6.67')
            ->and(Money::avg($amounts, Rounding::Floor)->toDecimal())->toBe('6.67')
            ->and(Money::avg([Money::of('0.01'), Money::of('0.02')], Rounding::Floor)->toDecimal())->toBe('0.01')
            ->and(Money::avg([Money::of('0.01'), Money::of('0.02')])->toDecimal())->toBe('0.02');
    });

    it('rejects empty, mixed and mismatched aggregates', function (): void {
        expect(fn () => Money::sum([]))->toThrow(InvalidMoneyException::class, 'Money::sum() needs at least one Money instance.')
            ->and(fn () => Money::max([]))->toThrow(InvalidMoneyException::class, 'Money::max() needs at least one Money instance.')
            ->and(fn () => Money::min(['10']))->toThrow(InvalidMoneyException::class, 'Money::min() only accepts Laranex\LaravelMoney\Money instances, string given.')
            ->and(fn () => Money::avg([Money::of('1'), Money::of('1', 'EUR')]))->toThrow(CurrencyMismatchException::class);
    });
});

describe('percentages and ratios', function (): void {
    it('takes a percentage of an amount', function (): void {
        expect(Money::of('200')->percent(15)->toDecimal())->toBe('30.00')
            ->and(Money::of('200')->percent('7.5')->toDecimal())->toBe('15.00')
            ->and(Money::of('19.99')->percent('7')->toDecimal())->toBe('1.40')
            ->and(Money::of('19.99')->percent('7', Rounding::Floor)->toDecimal())->toBe('1.39')
            ->and(Money::of('1000', 'JPY')->percent('8.25')->toDecimal())->toBe('83')
            ->and(Money::of('10', 'KWD')->percent('0.125')->toDecimal())->toBe('0.013');
    });

    it('adds and subtracts percentages', function (): void {
        expect(Money::of('100')->addPercent(7)->toDecimal())->toBe('107.00')
            ->and(Money::of('19.99')->addPercent('8.875')->toDecimal())->toBe('21.76')
            ->and(Money::of('100')->subtractPercent(15)->toDecimal())->toBe('85.00')
            ->and(Money::of('9.99')->subtractPercent('33.333', Rounding::Ceiling)->toDecimal())->toBe('6.66');
    });

    it('says what percentage one amount is of another', function (): void {
        expect(Money::of('25')->percentageOf(Money::of('200')))->toBe('12.50')
            ->and(Money::of('1')->percentageOf(Money::of('3')))->toBe('33.33')
            ->and(Money::of('2')->percentageOf(Money::of('3')))->toBe('66.67')
            ->and(Money::of('2')->percentageOf(Money::of('3'), 4))->toBe('66.6667')
            ->and(Money::of('2')->percentageOf(Money::of('3'), 0, Rounding::Floor))->toBe('66')
            ->and(Money::of('300')->percentageOf(Money::of('200')))->toBe('150.00')
            ->and(Money::of('-50', 'JPY')->percentageOf(Money::of('200', 'JPY')))->toBe('-25.00')
            ->and(fn () => Money::of('1')->percentageOf(Money::zero()))->toThrow(InvalidMoneyException::class, 'Cannot divide money by zero.')
            ->and(fn () => Money::of('1')->percentageOf(Money::of('1', 'EUR')))->toThrow(CurrencyMismatchException::class)
            ->and(fn () => Money::of('1')->percentageOf(Money::of('3'), -1))->toThrow(InvalidMoneyException::class, 'The scale must be zero or positive, -1 given.');
    });

    it('computes ratios', function (): void {
        expect(Money::of('50')->ratioOf(Money::of('200')))->toBe('0.2500')
            ->and(Money::of('1')->ratioOf(Money::of('3'), 6))->toBe('0.333333')
            ->and(Money::of('2')->ratioOf(Money::of('3'), 2, Rounding::Floor))->toBe('0.66')
            ->and(fn () => Money::of('1')->ratioOf(Money::zero()))->toThrow(InvalidMoneyException::class)
            ->and(fn () => Money::of('1')->ratioOf(Money::of('1', 'EUR')))->toThrow(CurrencyMismatchException::class)
            ->and(fn () => Money::of('1')->ratioOf(Money::of('3'), -2))->toThrow(InvalidMoneyException::class, 'The scale must be zero or positive, -2 given.');
    });
});

describe('allocation', function (): void {
    it('splits without losing a minor unit', function (): void {
        $parts = Money::of('100.00')->split(3);

        expect(array_map(fn (Money $money): string => $money->toDecimal(), $parts))->toBe(['33.34', '33.33', '33.33'])
            ->and(Money::sum($parts)->toDecimal())->toBe('100.00');
    });

    it('splits zero-decimal and three-decimal currencies', function (): void {
        expect(array_map(fn (Money $money): string => $money->amount(), Money::of('1000', 'JPY')->split(3)))->toBe(['334', '333', '333'])
            ->and(array_map(fn (Money $money): string => $money->toDecimal(), Money::of('1', 'KWD')->split(6)))->toBe(['0.167', '0.167', '0.167', '0.167', '0.166', '0.166'])
            ->and(array_map(fn (Money $money): string => $money->toDecimal(), Money::of('-100')->split(3)))->toBe(['-33.34', '-33.33', '-33.33'])
            ->and(Money::of('5')->split(1)[0]->toDecimal())->toBe('5.00');
    });

    it('allocates by ratio and keeps keys', function (): void {
        $shares = Money::of('100.00')->allocate(['owner' => 70, 'agent' => 20, 'platform' => 10]);

        expect(array_map(fn (Money $money): string => $money->toDecimal(), $shares))->toBe(['owner' => '70.00', 'agent' => '20.00', 'platform' => '10.00']);
    });

    it('gives leftover units to the largest remainders, earlier keys first', function (): void {
        $shares = Money::ofMinor(5)->allocate(['a' => 3, 'b' => 7]);
        $decimalRatios = Money::ofMinor(10)->allocate(['x' => '0.3', 'y' => '0.3', 'z' => '0.4']);
        $ties = Money::ofMinor(2)->allocate([1, 1, 1]);

        expect(array_map(fn (Money $money): string => $money->amount(), $shares))->toBe(['a' => '2', 'b' => '3'])
            ->and(array_map(fn (Money $money): string => $money->amount(), $decimalRatios))->toBe(['x' => '3', 'y' => '3', 'z' => '4'])
            ->and(array_map(fn (Money $money): string => $money->amount(), $ties))->toBe(['1', '1', '0'])
            ->and(array_map(fn (Money $money): string => $money->amount(), Money::ofMinor(100)->allocate([0, 1])))->toBe(['0', '100']);
    });

    it('allocates negative amounts like their absolute value', function (): void {
        expect(array_map(fn (Money $money): string => $money->toDecimal(), Money::of('-0.05')->allocate([1, 1, 1])))->toBe(['-0.02', '-0.02', '-0.01'])
            ->and(array_map(fn (Money $money): string => $money->toDecimal(), Money::of('-1', 'KWD')->allocate(['a' => 1, 'b' => 2])))->toBe(['a' => '-0.333', 'b' => '-0.667']);
    });

    it('never gives a leftover unit to a zero ratio', function (): void {
        expect(array_map(fn (Money $money): string => $money->amount(), Money::ofMinor(5)->allocate([0, 1, 1, 0, 1])))->toBe(['0', '2', '2', '0', '1']);
    });

    it('keeps every minor unit for any amount and ratios', function (): void {
        mt_srand(4);

        for ($i = 0; $i < 200; $i++) {
            $money = Money::ofMinor(mt_rand(-1_000_000, 1_000_000), ['USD', 'JPY', 'KWD'][$i % 3]);
            $ratios = array_map(fn (): string => mt_rand(0, 50).'.'.mt_rand(0, 99), range(0, mt_rand(0, 6)));
            $ratios[] = (string) mt_rand(1, 9);
            $shares = $money->allocate($ratios);

            expect(Money::sum($shares)->equals($money))->toBeTrue()
                ->and(array_keys($shares))->toBe(array_keys($ratios));

            foreach ($shares as $share) {
                expect($share->isNegative() && $money->isPositive())->toBeFalse()
                    ->and($share->isPositive() && $money->isNegative())->toBeFalse();
            }
        }
    });

    it('matches moneyphp allocation for small amounts', function (): void {
        $ratios = [3, 5, 11, 7];
        $ours = array_map(fn (Money $money): string => $money->amount(), Money::ofMinor(1001)->allocate($ratios));
        $theirs = array_map(fn (MoneyPhp $money): string => $money->getAmount(), MoneyPhp::USD(1001)->allocate($ratios));

        expect($ours)->toBe($theirs);
    });

    it('rejects invalid splits and ratios', function (): void {
        expect(fn () => Money::of('1')->split(0))->toThrow(InvalidMoneyException::class, 'Money can only be split into one or more parts, 0 given.')
            ->and(fn () => Money::of('1')->allocate([]))->toThrow(InvalidMoneyException::class, 'Cannot allocate money: at least one ratio is required.')
            ->and(fn () => Money::of('1')->allocate([1, -1]))->toThrow(InvalidMoneyException::class, 'ratios must be zero or positive.')
            ->and(fn () => Money::of('1')->allocate([0, '0.0']))->toThrow(InvalidMoneyException::class, 'the sum of the ratios must be greater than zero.');
    });
});

describe('rounding', function (): void {
    it('rounds to fewer decimals and keeps the currency precision', function (): void {
        expect(Money::of('12.34')->roundTo(0)->toDecimal())->toBe('12.00')
            ->and(Money::of('12.50')->roundTo(0)->toDecimal())->toBe('13.00')
            ->and(Money::of('12.50')->roundTo(0, Rounding::HalfEven)->toDecimal())->toBe('12.00')
            ->and(Money::of('12.34')->roundTo(1, Rounding::Ceiling)->toDecimal())->toBe('12.40')
            ->and(Money::of('15', 'JPY')->roundTo(-1)->amount())->toBe('20')
            ->and(Money::of('12.345', 'KWD')->roundTo(2)->toDecimal())->toBe('12.350')
            ->and(Money::of('12.34')->roundTo(2)->toDecimal())->toBe('12.34')
            ->and(Money::of('12.34')->roundTo(5)->toDecimal())->toBe('12.34');
    });

    it('applies every rounding mode to ties and non-ties', function (Rounding $rounding, array $expected): void {
        $results = array_map(
            fn (string $value): string => Money::of($value, 'JPY', $rounding)->amount(),
            ['2.5', '3.5', '-2.5', '-3.5', '2.4', '-2.6', '2.1', '-2.1'],
        );

        expect($results)->toBe($expected);
    })->with([
        'half up' => [Rounding::HalfUp, ['3', '4', '-3', '-4', '2', '-3', '2', '-2']],
        'half down' => [Rounding::HalfDown, ['2', '3', '-2', '-3', '2', '-3', '2', '-2']],
        'half even' => [Rounding::HalfEven, ['2', '4', '-2', '-4', '2', '-3', '2', '-2']],
        'half odd' => [Rounding::HalfOdd, ['3', '3', '-3', '-3', '2', '-3', '2', '-2']],
        'half positive infinity' => [Rounding::HalfPositiveInfinity, ['3', '4', '-2', '-3', '2', '-3', '2', '-2']],
        'half negative infinity' => [Rounding::HalfNegativeInfinity, ['2', '3', '-3', '-4', '2', '-3', '2', '-2']],
        'ceiling' => [Rounding::Ceiling, ['3', '4', '-2', '-3', '3', '-2', '3', '-2']],
        'floor' => [Rounding::Floor, ['2', '3', '-3', '-4', '2', '-3', '2', '-3']],
    ]);

    it('matches moneyphp for every rounding mode', function (Rounding $rounding): void {
        foreach (['2.5', '3.5', '-2.5', '-3.5', '2.4', '-2.6', '0.5', '-0.5'] as $value) {
            $ours = Money::of($value, 'JPY', $rounding)->amount();
            $theirs = MoneyPhp::JPY(10)->multiply($value, $rounding->toMoneyPhp())->divide('10', $rounding->toMoneyPhp())->getAmount();

            expect($ours)->toBe($theirs, "{$rounding->value} {$value}");
        }
    })->with(Rounding::cases());

    it('uses the configured default rounding', function (): void {
        config()->set('money.rounding', 'floor');

        expect(Money::of('10')->dividedBy(3)->toDecimal())->toBe('3.33')
            ->and(Money::of('20')->dividedBy(3)->toDecimal())->toBe('6.66');

        config()->set('money.rounding', Rounding::Ceiling);

        expect(Money::of('10')->dividedBy(3)->toDecimal())->toBe('3.34');

        config()->set('money.rounding', 'sideways');

        expect(fn () => Money::of('10')->dividedBy(3))->toThrow(InvalidMoneyException::class, 'The config value [money.rounding] must be one of "half_up"');
    });

    it('maps rounding modes to moneyphp constants and back', function (): void {
        foreach (Rounding::cases() as $rounding) {
            expect(Rounding::fromMoneyPhp($rounding->toMoneyPhp()))->toBe($rounding);
        }

        expect(Rounding::Ceiling->toMoneyPhp())->toBe(MoneyPhp::ROUND_UP)
            ->and(Rounding::Floor->toMoneyPhp())->toBe(MoneyPhp::ROUND_DOWN)
            ->and(fn () => Rounding::fromMoneyPhp(99))->toThrow(ValueError::class);
    });
});

describe('macros', function (): void {
    it('is macroable', function (): void {
        Money::macro('withVat', fn (): Money => $this->addPercent(20));

        expect(Money::of('10')->withVat()->toDecimal())->toBe('12.00');
    });
});
