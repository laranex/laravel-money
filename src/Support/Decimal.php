<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Support;

use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\MoneyParseException;
use Laranex\LaravelMoney\Rounding;
use LogicException;

/**
 * Exact decimal arithmetic on numeric strings (bcmath, scale 0 only).
 *
 * Every value is either an integer string or a fraction of two integer
 * strings, so nothing is ever converted to a float.
 *
 * @internal
 */
final class Decimal
{
    /**
     * Parse an int or a decimal string into its sign, integer digits and
     * fraction digits. The integer part may be grouped with one separator
     * (comma, space, no-break space or narrow no-break space) used
     * consistently: Western groups of three (1,234,567) or Indian grouping
     * (12,34,567). The decimal separator is always a dot.
     *
     * @return array{negative: bool, integer: numeric-string, fraction: string}
     */
    public static function parse(int|string|float $value): array
    {
        if (is_float($value)) {
            throw InvalidMoneyException::float($value);
        }

        $input = (string) $value;
        $body = trim($input);
        $negative = false;

        if ($body !== '' && ($body[0] === '-' || $body[0] === '+')) {
            $negative = $body[0] === '-';
            $body = substr($body, 1);
        }

        $parts = explode('.', $body);

        if (count($parts) > 2) {
            throw MoneyParseException::invalid($input);
        }

        $integer = $parts[0];
        $fraction = $parts[1] ?? '';

        if (preg_match('/[, \x{00A0}\x{202F}]/u', $integer) === 1) {
            if (! self::isGrouped($integer)) {
                throw MoneyParseException::invalid($input);
            }

            $integer = str_replace([',', ' ', "\u{00A0}", "\u{202F}"], '', $integer);
        }

        if (preg_match('/^\d+$/', $integer) !== 1 || (count($parts) === 2 && preg_match('/^\d+$/', $fraction) !== 1)) {
            throw MoneyParseException::invalid($input);
        }

        $integer = self::normalize($integer);

        return [
            'negative' => $negative && ($integer !== '0' || trim($fraction, '0') !== ''),
            'integer' => $integer,
            'fraction' => $fraction,
        ];
    }

    /**
     * Whether $integer is digits grouped by a single separator: Western
     * groups of three ("1,234,567") or Indian grouping ("12,34,567": the
     * last group has three digits, earlier groups two).
     */
    private static function isGrouped(string $integer): bool
    {
        return preg_match('/^[0-9]{1,3}([, \x{00A0}\x{202F}])[0-9]{3}(?:\1[0-9]{3})*$/u', $integer) === 1
            || preg_match('/^[0-9]{1,2}([, \x{00A0}\x{202F}])[0-9]{2}(?:\1[0-9]{2})*\1[0-9]{3}$/u', $integer) === 1;
    }

    /**
     * Turn a decimal amount into minor units with the given precision.
     *
     * Extra fraction digits that are all zeros are dropped; any other extra
     * digits throw unless a rounding mode is given.
     *
     * @return numeric-string
     */
    public static function toMinor(int|string|float $value, int $precision, ?Rounding $rounding, string $currency): string
    {
        $parsed = self::parse($value);
        $fraction = $parsed['fraction'];
        $sign = $parsed['negative'] ? '-' : '';

        if (strlen($fraction) <= $precision) {
            return self::normalize($sign.$parsed['integer'].str_pad($fraction, $precision, '0'));
        }

        $extra = substr($fraction, $precision);

        if (trim($extra, '0') === '') {
            return self::normalize($sign.$parsed['integer'].substr($fraction, 0, $precision));
        }

        if ($rounding === null) {
            throw MoneyParseException::tooManyDecimals(trim((string) $value), $currency, $precision, strlen($fraction));
        }

        return self::divide(
            self::normalize($sign.$parsed['integer'].$fraction),
            self::pow10(strlen($extra)),
            $rounding,
        );
    }

    /**
     * Format minor units as a decimal string with exactly $precision decimals.
     *
     * @param  numeric-string  $minor
     * @return numeric-string
     */
    public static function fromMinor(string $minor, int $precision): string
    {
        $negative = $minor[0] === '-';
        $digits = ltrim($minor, '-');

        if ($precision > 0) {
            $digits = str_pad($digits, $precision + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$precision).'.'.substr($digits, -$precision);
        }

        return self::numeric(($negative ? '-' : '').$digits);
    }

    /**
     * A decimal number as an exact fraction of two integers.
     *
     * @return array{numeric-string, numeric-string} [numerator, denominator]
     */
    public static function fraction(int|string|float $value): array
    {
        $parsed = self::parse($value);

        return [
            self::normalize(($parsed['negative'] ? '-' : '').$parsed['integer'].$parsed['fraction']),
            self::pow10(strlen($parsed['fraction'])),
        ];
    }

    /**
     * Divide two integers and round the quotient to an integer, exactly.
     *
     * @param  numeric-string  $numerator
     * @param  numeric-string  $denominator
     * @return numeric-string
     */
    public static function divide(string $numerator, string $denominator, Rounding $rounding): string
    {
        if (self::isZero($denominator)) {
            throw InvalidMoneyException::divisionByZero();
        }

        if (bccomp($denominator, '0', 0) < 0) {
            $numerator = self::negate($numerator);
            $denominator = self::negate($denominator);
        }

        $quotient = self::normalize(bcdiv($numerator, $denominator, 0));
        $remainder = bcmod($numerator, $denominator, 0);

        if (self::isZero($remainder)) {
            return $quotient;
        }

        $positive = bccomp($numerator, '0', 0) > 0;
        $away = self::normalize($positive ? bcadd($quotient, '1', 0) : bcsub($quotient, '1', 0));
        $half = bccomp(bcmul(ltrim($remainder, '-'), '2', 0), $denominator, 0);

        $roundAway = match ($rounding) {
            Rounding::Ceiling => $positive,
            Rounding::Floor => ! $positive,
            default => $half > 0 || ($half === 0 && match ($rounding) {
                Rounding::HalfUp => true,
                Rounding::HalfDown => false,
                Rounding::HalfEven => ((int) substr($quotient, -1)) % 2 === 1,
                Rounding::HalfOdd => ((int) substr($quotient, -1)) % 2 === 0,
                Rounding::HalfPositiveInfinity => $positive,
                Rounding::HalfNegativeInfinity => ! $positive,
            }),
        };

        return $roundAway ? $away : $quotient;
    }

    /**
     * Divide two integers and return a decimal string with $scale decimals.
     *
     * @param  numeric-string  $numerator
     * @param  numeric-string  $denominator
     * @return numeric-string
     */
    public static function quotient(string $numerator, string $denominator, int $scale, Rounding $rounding): string
    {
        return self::fromMinor(self::divide(bcmul($numerator, self::pow10($scale), 0), $denominator, $rounding), $scale);
    }

    /**
     * @return numeric-string
     */
    public static function pow10(int $exponent): string
    {
        return self::numeric('1'.str_repeat('0', max(0, $exponent)));
    }

    /**
     * @param  numeric-string  $value
     */
    public static function isZero(string $value): bool
    {
        return bccomp($value, '0', 0) === 0;
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function negate(string $value): string
    {
        return self::normalize(bcmul($value, '-1', 0));
    }

    /**
     * Strip leading zeros and turn "-0" into "0".
     *
     * @return numeric-string
     */
    public static function normalize(string $value): string
    {
        $negative = $value !== '' && $value[0] === '-';
        $digits = ltrim(ltrim($value, '-+'), '0');

        if ($digits === '') {
            return '0';
        }

        return self::numeric(($negative ? '-' : '').$digits);
    }

    /**
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new LogicException(sprintf('"%s" is not numeric.', $value));
        }

        return $value;
    }
}
