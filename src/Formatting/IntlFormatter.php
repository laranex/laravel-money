<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Formatting;

use IntlChar;
use Laranex\LaravelMoney\Support\Decimal;
use NumberFormatter;

/**
 * Locale-aware currency formatting that never goes through a float.
 *
 * NumberFormatter only formats ints and floats, so large or precise
 * amounts would lose digits (moneyphp's IntlMoneyFormatter casts to float).
 * Instead, the locale's symbols, digits, grouping and the currency
 * prefix/suffix are taken from ICU, and the exact decimal string is
 * assembled from them. The affixes come from ICU formatting the exact
 * values 1 and -1, so currency spacing and sign placement match ICU.
 */
final class IntlFormatter implements Formatter
{
    /**
     * @var array<string, array{positive: array{string, string}, negative: array{string, string}, digits: list<string>, decimal: string, grouping: string, primary: int, secondary: int, minimum: int}>
     */
    private array $patterns = [];

    public function format(string $decimal, string $currency, int $precision, string $locale): string
    {
        $pattern = $this->pattern($currency, $precision, $locale);
        $negative = $decimal[0] === '-';
        [$integer, $fraction] = array_pad(explode('.', ltrim($decimal, '-'), 2), 2, '');

        if ($pattern['primary'] > 0 && strlen($integer) >= $pattern['primary'] + $pattern['minimum']) {
            $groups = [substr($integer, -$pattern['primary'])];
            $rest = substr($integer, 0, -$pattern['primary']);

            while (strlen($rest) > $pattern['secondary']) {
                array_unshift($groups, substr($rest, -$pattern['secondary']));
                $rest = substr($rest, 0, -$pattern['secondary']);
            }

            if ($rest !== '') {
                array_unshift($groups, $rest);
            }

            $integer = implode("\0", $groups);
        }

        $core = strtr($integer.($fraction !== '' ? "\1".$fraction : ''), [
            ...array_combine(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $pattern['digits']),
            "\0" => $pattern['grouping'],
            "\1" => $pattern['decimal'],
        ]);

        [$prefix, $suffix] = $negative ? $pattern['negative'] : $pattern['positive'];

        return $prefix.$core.$suffix;
    }

    /**
     * @return array{positive: array{string, string}, negative: array{string, string}, digits: list<string>, decimal: string, grouping: string, primary: int, secondary: int, minimum: int}
     */
    private function pattern(string $currency, int $precision, string $locale): array
    {
        $key = $locale.'|'.$currency.'|'.$precision;

        if (isset($this->patterns[$key])) {
            return $this->patterns[$key];
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);

        $zero = (int) IntlChar::ord((string) $formatter->getSymbol(NumberFormatter::ZERO_DIGIT_SYMBOL));
        $digits = array_map(static fn (int $offset): string => (string) IntlChar::chr($zero + $offset), range(0, 9));

        $grouping = (string) $formatter->getSymbol(NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL);
        $primary = $formatter->getAttribute(NumberFormatter::GROUPING_USED) ? (int) $formatter->getAttribute(NumberFormatter::GROUPING_SIZE) : 0;
        $secondary = (int) $formatter->getAttribute(NumberFormatter::SECONDARY_GROUPING_SIZE);

        return $this->patterns[$key] = [
            'positive' => $this->affixes((string) $formatter->formatCurrency(1, $currency), $digits),
            'negative' => $this->affixes((string) $formatter->formatCurrency(-1, $currency), $digits),
            'digits' => $digits,
            'decimal' => (string) $formatter->getSymbol(NumberFormatter::MONETARY_SEPARATOR_SYMBOL),
            'grouping' => $grouping,
            'primary' => $primary,
            'secondary' => $secondary > 0 ? $secondary : $primary,
            'minimum' => $this->minimumGroupingDigits($formatter, $primary, $grouping),
        ];
    }

    /**
     * Split a formatted probe into the text before its first digit and after its last.
     *
     * @param  list<string>  $digits
     * @return array{string, string}
     */
    private function affixes(string $formatted, array $digits): array
    {
        $characters = preg_split('//u', $formatted, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $positions = array_keys(array_filter($characters, static fn (string $character): bool => in_array($character, $digits, true)));

        if ($positions === []) {
            return ['', ''];
        }

        return [
            implode('', array_slice($characters, 0, min($positions))),
            implode('', array_slice($characters, max($positions) + 1)),
        ];
    }

    /**
     * How many digits must precede the first grouping separator before it
     * is used (CLDR minimumGroupingDigits, e.g. 2 for Spanish: "1234" but "12.345").
     */
    private function minimumGroupingDigits(NumberFormatter $formatter, int $primary, string $grouping): int
    {
        if ($primary === 0 || $grouping === '') {
            return 1;
        }

        for ($minimum = 1; $minimum < 4; $minimum++) {
            $probe = (string) $formatter->format((int) Decimal::pow10($primary + $minimum - 1));

            if (str_contains($probe, $grouping)) {
                return $minimum;
            }
        }

        return $minimum;
    }
}
