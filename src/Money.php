<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\Macroable;
use JsonSerializable;
use Laranex\LaravelMoney\Casts\AsMoney;
use Laranex\LaravelMoney\Casts\MoneyCast;
use Laranex\LaravelMoney\Exceptions\CurrencyMismatchException;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Support\Decimal;
use Money\Currency;
use Money\Money as MoneyPhp;
use Stringable;

/**
 * An immutable amount of money in a currency, stored as integer minor units.
 *
 * Precision always comes from the currency (USD 2, JPY 0, KWD 3...).
 * Amounts are ints or numeric strings, never floats. Wherever a method
 * accepts Money|int|string, an int or string is a decimal amount in this
 * money's currency: $price->plus('2.50').
 *
 * @implements Arrayable<string, string>
 */
final class Money implements Arrayable, Castable, JsonSerializable, Stringable
{
    use Macroable;

    /**
     * The largest scale percentageOf() and ratioOf() accept, and the largest
     * number of decimals (either sign) roundTo() accepts, so no call can
     * force a huge power-of-ten computation.
     */
    public const MAX_SCALE = 100;

    private function __construct(
        private readonly MoneyPhp $money,
        private readonly int $precision,
    ) {}

    /**
     * Money from a decimal amount: "1234.50", "1,234.50", "-0.5" or 1234.
     *
     * Parsing is strict: more decimals than the currency allows throw a
     * MoneyParseException unless a rounding mode is given.
     */
    public static function of(int|string|float $amount, Currency|string|null $currency = null, ?Rounding $rounding = null): self
    {
        $currency = self::manager()->currency($currency);
        $precision = self::manager()->currencies()->precision($currency);

        return new self(new MoneyPhp(Decimal::toMinor($amount, $precision, $rounding, $currency->getCode()), $currency), $precision);
    }

    /**
     * Money from an amount in minor units: Money::ofMinor(123450, 'USD') is 1,234.50 USD.
     */
    public static function ofMinor(int|string|float $minor, Currency|string|null $currency = null): self
    {
        if (is_float($minor)) {
            throw InvalidMoneyException::float($minor);
        }

        $minor = trim((string) $minor);

        if (preg_match('/^[+-]?\d+$/', $minor) !== 1) {
            throw InvalidMoneyException::invalidMinorAmount($minor);
        }

        $currency = self::manager()->currency($currency);

        return new self(new MoneyPhp(Decimal::normalize($minor), $currency), self::manager()->currencies()->precision($currency));
    }

    /**
     * Zero in the given or default currency.
     */
    public static function zero(Currency|string|null $currency = null): self
    {
        return self::ofMinor(0, $currency);
    }

    /**
     * Wrap a moneyphp/money Money object. Its currency must be known to the registry.
     */
    public static function fromMoneyPhp(MoneyPhp $money): self
    {
        return self::ofMinor($money->getAmount(), $money->getCurrency());
    }

    /**
     * The sum of one or more amounts in the same currency.
     *
     * @param  iterable<mixed>  $monies
     */
    public static function sum(iterable $monies): self
    {
        $list = self::list($monies, 'sum');
        $total = array_shift($list);

        return $list === [] ? $total : $total->plus(...$list);
    }

    /**
     * The smallest of one or more amounts in the same currency.
     *
     * @param  iterable<mixed>  $monies
     */
    public static function min(iterable $monies): self
    {
        $list = self::list($monies, 'min');
        $result = array_shift($list);

        foreach ($list as $money) {
            if ($money->lessThan($result)) {
                $result = $money;
            }
        }

        return $result;
    }

    /**
     * The largest of one or more amounts in the same currency.
     *
     * @param  iterable<mixed>  $monies
     */
    public static function max(iterable $monies): self
    {
        $list = self::list($monies, 'max');
        $result = array_shift($list);

        foreach ($list as $money) {
            if ($money->greaterThan($result)) {
                $result = $money;
            }
        }

        return $result;
    }

    /**
     * The average of one or more amounts in the same currency, rounded to a minor unit.
     *
     * @param  iterable<mixed>  $monies
     */
    public static function avg(iterable $monies, ?Rounding $rounding = null): self
    {
        $list = self::list($monies, 'avg');

        return self::sum($list)->dividedBy(count($list), $rounding);
    }

    /**
     * The amount in minor units, e.g. "123450" for 1,234.50 USD.
     *
     * @return numeric-string
     */
    public function amount(): string
    {
        return $this->money->getAmount();
    }

    /**
     * The amount as a decimal string with the currency's precision, e.g. "1234.50".
     *
     * @return numeric-string
     */
    public function toDecimal(): string
    {
        return Decimal::fromMinor($this->amount(), $this->precision);
    }

    /**
     * The currency, e.g. Money\Currency('USD'); getCode() returns "USD".
     */
    public function currency(): Currency
    {
        return $this->money->getCurrency();
    }

    /**
     * The number of decimal places of the currency.
     */
    public function precision(): int
    {
        return $this->precision;
    }

    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }

    /**
     * Whether every given amount has this money's currency: the same code
     * and the same precision.
     */
    public function isSameCurrency(self ...$others): bool
    {
        foreach ($others as $other) {
            if (! $other->currency()->equals($this->currency()) || $other->precision !== $this->precision) {
                return false;
            }
        }

        return true;
    }

    /**
     * Same currency and same amount. Different currencies are never equal;
     * an operand that is not a valid amount, such as a float or "abc", throws.
     */
    public function equals(self|int|string|float $other): bool
    {
        $other = $this->operand($other);

        return $this->isSameCurrency($other) && $this->money->equals($other->money);
    }

    /**
     * -1, 0 or 1 when this is less than, equal to or greater than $other.
     */
    public function compare(self|int|string|float $other): int
    {
        return $this->money->compare($this->sameCurrency($this->operand($other))->money);
    }

    public function greaterThan(self|int|string|float $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function greaterThanOrEqual(self|int|string|float $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function lessThan(self|int|string|float $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function lessThanOrEqual(self|int|string|float $other): bool
    {
        return $this->compare($other) <= 0;
    }

    /**
     * Add amounts in the same currency: $price->plus($shipping, '2.50').
     */
    public function plus(self|int|string|float ...$addends): self
    {
        $amount = $this->amount();

        foreach ($addends as $addend) {
            $amount = bcadd($amount, $this->sameCurrency($this->operand($addend))->amount(), 0);
        }

        return $this->withAmount($amount);
    }

    /**
     * Subtract amounts in the same currency: $total->minus($discount).
     */
    public function minus(self|int|string|float ...$subtrahends): self
    {
        $amount = $this->amount();

        foreach ($subtrahends as $subtrahend) {
            $amount = bcsub($amount, $this->sameCurrency($this->operand($subtrahend))->amount(), 0);
        }

        return $this->withAmount($amount);
    }

    /**
     * Multiply by an int or decimal string ("1.5"), rounding to a minor unit.
     */
    public function times(int|string|float $multiplier, ?Rounding $rounding = null): self
    {
        [$numerator, $denominator] = Decimal::fraction($multiplier);

        return $this->withAmount(Decimal::divide(bcmul($this->amount(), $numerator, 0), $denominator, $this->rounding($rounding)));
    }

    /**
     * Divide by an int or decimal string ("1.5"), rounding to a minor unit.
     */
    public function dividedBy(int|string|float $divisor, ?Rounding $rounding = null): self
    {
        [$numerator, $denominator] = Decimal::fraction($divisor);

        return $this->withAmount(Decimal::divide(bcmul($this->amount(), $denominator, 0), $numerator, $this->rounding($rounding)));
    }

    /**
     * The remainder after dividing by another amount in the same currency.
     */
    public function mod(self|int|string|float $divisor): self
    {
        $divisor = $this->sameCurrency($this->operand($divisor));

        if ($divisor->isZero()) {
            throw InvalidMoneyException::divisionByZero();
        }

        return $this->withAmount(Decimal::normalize(bcmod($this->amount(), $divisor->amount(), 0)));
    }

    public function negated(): self
    {
        return $this->withAmount(Decimal::negate($this->amount()));
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negated() : $this;
    }

    /**
     * $percent percent of this amount: Money::of('200')->percent('7.5') is 15.00.
     */
    public function percent(int|string|float $percent, ?Rounding $rounding = null): self
    {
        [$numerator, $denominator] = Decimal::fraction($percent);

        return $this->withAmount(Decimal::divide(bcmul($this->amount(), $numerator, 0), bcmul($denominator, '100', 0), $this->rounding($rounding)));
    }

    /**
     * This amount plus $percent percent of it, e.g. adding 7% tax.
     */
    public function addPercent(int|string|float $percent, ?Rounding $rounding = null): self
    {
        return $this->plus($this->percent($percent, $rounding));
    }

    /**
     * This amount minus $percent percent of it, e.g. a 15% discount.
     */
    public function subtractPercent(int|string|float $percent, ?Rounding $rounding = null): self
    {
        return $this->minus($this->percent($percent, $rounding));
    }

    /**
     * What percentage this amount is of $total, as a decimal string with
     * $scale decimals: Money::of('25')->percentageOf(Money::of('200'), 2) is
     * "12.50". The scale must be between 0 and MAX_SCALE.
     *
     * @return numeric-string
     */
    public function percentageOf(self $total, int $scale, ?Rounding $rounding = null): string
    {
        return $this->quotientOf(bcmul($this->amount(), '100', 0), $total, $scale, $rounding);
    }

    /**
     * This amount divided by $other, as a decimal string with $scale
     * decimals: Money::of('50')->ratioOf(Money::of('200'), 4) is "0.2500".
     * The scale must be between 0 and MAX_SCALE.
     *
     * @return numeric-string
     */
    public function ratioOf(self $other, int $scale, ?Rounding $rounding = null): string
    {
        return $this->quotientOf($this->amount(), $other, $scale, $rounding);
    }

    /**
     * Split into $parts equal amounts without losing a minor unit; the
     * remainder goes to the first parts: 100.00 / 3 is 33.34, 33.33, 33.33.
     *
     * @return list<self>
     */
    public function split(int $parts): array
    {
        if ($parts < 1) {
            throw InvalidMoneyException::invalidParts($parts);
        }

        return array_values($this->allocate(array_fill(0, $parts, 1)));
    }

    /**
     * Allocate by ratios (ints or decimal strings), keeping the keys and
     * every minor unit: allocate(['owner' => 70, 'agent' => 30]).
     *
     * Each part gets its share rounded down; the leftover minor units go,
     * one each, to the parts with the largest remainders. Ties go to keys in
     * ascending order (for a list, the earlier parts), like goravel-money's
     * AllocateMap, so the result never depends on insertion order. Negative
     * amounts are allocated as their absolute value and negated.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int|string|float>  $ratios
     * @return array<TKey, self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw InvalidMoneyException::invalidRatios('at least one ratio is required.');
        }

        $fractions = array_map(static fn (int|string|float $ratio): array => Decimal::fraction($ratio), $ratios);
        $scale = max(array_map(static fn (array $fraction): int => strlen($fraction[1]) - 1, $fractions));
        $weights = [];
        $total = '0';

        foreach ($fractions as $key => [$numerator, $denominator]) {
            if (bccomp($numerator, '0', 0) < 0) {
                throw InvalidMoneyException::invalidRatios('ratios must be zero or positive.');
            }

            $weights[$key] = bcmul($numerator, bcdiv(Decimal::pow10($scale), $denominator, 0), 0);
            $total = bcadd($total, $weights[$key], 0);
        }

        if (Decimal::isZero($total)) {
            throw InvalidMoneyException::invalidRatios('the sum of the ratios must be greater than zero.');
        }

        $absolute = ltrim($this->amount(), '-');
        $shares = [];
        $remainders = [];
        $left = $absolute;

        foreach ($weights as $key => $weight) {
            $product = bcmul($absolute, $weight, 0);
            $shares[$key] = bcdiv($product, $total, 0);
            $remainders[$key] = bcmod($product, $total, 0);
            $left = bcsub($left, $shares[$key], 0);
        }

        $order = array_keys($remainders);
        usort($order, static fn (int|string $a, int|string $b): int => bccomp($remainders[$b], $remainders[$a], 0) ?: self::compareKeys($a, $b));

        foreach (array_slice($order, 0, (int) $left) as $key) {
            $shares[$key] = bcadd($shares[$key], '1', 0);
        }

        $negative = $this->isNegative();

        return array_map(fn (string $share): self => $this->withAmount($negative ? Decimal::negate($share) : $share), $shares);
    }

    /**
     * Round to fewer decimals than the currency has, keeping minor-unit
     * storage: Money::of('12.34')->roundTo(0) is 12.00; roundTo(-1) is 10.00.
     * $decimals must be between -MAX_SCALE and MAX_SCALE.
     */
    public function roundTo(int $decimals, ?Rounding $rounding = null): self
    {
        if ($decimals < -self::MAX_SCALE || $decimals > self::MAX_SCALE) {
            throw InvalidMoneyException::invalidDecimals($decimals);
        }

        if ($decimals >= $this->precision) {
            return $this;
        }

        $unit = Decimal::pow10($this->precision - $decimals);

        return $this->withAmount(bcmul(Decimal::divide($this->amount(), $unit, $this->rounding($rounding)), $unit, 0));
    }

    /**
     * Format for display, e.g. "$1,234.50", using ext-intl when installed
     * ("USD 1234.50" otherwise). The locale defaults to money.locale, then
     * the app locale.
     */
    public function format(?string $locale = null): string
    {
        return self::manager()->format($this, $locale);
    }

    /**
     * The moneyphp/money Money object behind this amount.
     */
    public function toMoneyPhp(): MoneyPhp
    {
        return $this->money;
    }

    /**
     * The serialized shape, configured by money.serialization:
     * ['amount' => '123450', 'currency' => 'USD', 'decimal' => '1234.50', 'formatted' => '$1,234.50'].
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $options = self::manager()->serialization();
        $array = [
            'amount' => $options['amount'] === 'decimal' ? $this->toDecimal() : $this->amount(),
            'currency' => $this->currency()->getCode(),
        ];

        if ($options['include_decimal'] && $options['amount'] === 'minor') {
            $array['decimal'] = $this->toDecimal();
        }

        if ($options['include_formatted']) {
            $array['formatted'] = $this->format();
        }

        return $array;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The currency code and the decimal amount, "USD 1234.50". It never
     * depends on config or the locale; use format() for display.
     */
    public function __toString(): string
    {
        return $this->currency()->getCode().' '.$this->toDecimal();
    }

    /**
     * Cast an attribute to Money: 'price' => Money::class (integer minor
     * units, default currency) or Money::class.':USD'.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    public static function castUsing(array $arguments): MoneyCast
    {
        return AsMoney::castUsing($arguments);
    }

    /**
     * @param  numeric-string  $amount
     */
    private function withAmount(string $amount): self
    {
        return new self(new MoneyPhp(Decimal::normalize($amount), $this->money->getCurrency()), $this->precision);
    }

    private function operand(self|int|string|float $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new self(new MoneyPhp(Decimal::toMinor($value, $this->precision, null, $this->currency()->getCode()), $this->money->getCurrency()), $this->precision);
    }

    /**
     * $numerator divided by $other's amount, as a decimal string with $scale decimals.
     *
     * @param  numeric-string  $numerator
     * @return numeric-string
     */
    private function quotientOf(string $numerator, self $other, int $scale, ?Rounding $rounding): string
    {
        $this->sameCurrency($other);

        if ($other->isZero()) {
            throw InvalidMoneyException::divisionByZero();
        }

        if ($scale < 0 || $scale > self::MAX_SCALE) {
            throw InvalidMoneyException::invalidScale($scale);
        }

        return Decimal::quotient($numerator, $other->amount(), $scale, $this->rounding($rounding));
    }

    private function sameCurrency(self $other): self
    {
        if (! $this->isSameCurrency($other)) {
            throw CurrencyMismatchException::between($this, $other);
        }

        return $other;
    }

    /**
     * Allocation tie-break order: integer keys numerically, string keys
     * byte by byte, integer keys before string keys.
     */
    private static function compareKeys(int|string $a, int|string $b): int
    {
        if (is_int($a) && is_int($b)) {
            return $a <=> $b;
        }

        if (is_int($a) !== is_int($b)) {
            return is_int($a) ? -1 : 1;
        }

        return strcmp((string) $a, (string) $b);
    }

    private function rounding(?Rounding $rounding): Rounding
    {
        return $rounding ?? self::manager()->rounding();
    }

    /**
     * @param  iterable<mixed>  $monies
     * @return non-empty-list<self>
     */
    private static function list(iterable $monies, string $operation): array
    {
        $list = [];

        foreach ($monies as $money) {
            if (! $money instanceof self) {
                throw InvalidMoneyException::notMoneyInstance($operation, $money);
            }

            $list[] = $money;
        }

        if ($list === []) {
            throw InvalidMoneyException::emptyAggregate($operation);
        }

        return $list;
    }

    private static function manager(): LaravelMoney
    {
        return Container::getInstance()->make(LaravelMoney::class);
    }
}
