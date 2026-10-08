<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Money\Money as MoneyPhp;
use ValueError;

/**
 * How an amount that falls between two minor units is rounded.
 *
 * The "half" modes only differ when the amount is exactly halfway; every
 * other amount rounds to the nearest minor unit.
 */
enum Rounding: string
{
    /** Halfway values round away from zero: 2.5 -> 3, -2.5 -> -3. The default. */
    case HalfUp = 'half_up';

    /** Halfway values round toward zero: 2.5 -> 2, -2.5 -> -2. */
    case HalfDown = 'half_down';

    /** Halfway values round to the even neighbor (banker's rounding): 2.5 -> 2, 3.5 -> 4. */
    case HalfEven = 'half_even';

    /** Halfway values round to the odd neighbor: 2.5 -> 3, 3.5 -> 3. */
    case HalfOdd = 'half_odd';

    /** Halfway values round toward positive infinity: 2.5 -> 3, -2.5 -> -2. */
    case HalfPositiveInfinity = 'half_positive_infinity';

    /** Halfway values round toward negative infinity: 2.5 -> 2, -2.5 -> -3. */
    case HalfNegativeInfinity = 'half_negative_infinity';

    /** Always round toward positive infinity (ceil): 2.1 -> 3, -2.9 -> -2. */
    case Ceiling = 'ceiling';

    /** Always round toward negative infinity (floor): 2.9 -> 2, -2.1 -> -3. */
    case Floor = 'floor';

    /**
     * The matching moneyphp/money Money::ROUND_* constant.
     *
     * @return MoneyPhp::ROUND_*
     */
    public function toMoneyPhp(): int
    {
        return match ($this) {
            self::HalfUp => MoneyPhp::ROUND_HALF_UP,
            self::HalfDown => MoneyPhp::ROUND_HALF_DOWN,
            self::HalfEven => MoneyPhp::ROUND_HALF_EVEN,
            self::HalfOdd => MoneyPhp::ROUND_HALF_ODD,
            self::HalfPositiveInfinity => MoneyPhp::ROUND_HALF_POSITIVE_INFINITY,
            self::HalfNegativeInfinity => MoneyPhp::ROUND_HALF_NEGATIVE_INFINITY,
            self::Ceiling => MoneyPhp::ROUND_UP,
            self::Floor => MoneyPhp::ROUND_DOWN,
        };
    }

    /**
     * The case for a moneyphp/money Money::ROUND_* constant.
     */
    public static function fromMoneyPhp(int $mode): self
    {
        foreach (self::cases() as $case) {
            if ($case->toMoneyPhp() === $mode) {
                return $case;
            }
        }

        throw new ValueError(sprintf('%d is not a moneyphp/money rounding mode.', $mode));
    }
}
