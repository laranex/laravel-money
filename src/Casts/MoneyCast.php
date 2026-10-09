<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Casts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Exceptions\CurrencyMismatchException;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\LaravelMoney;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Support\Decimal;
use Money\Currency;
use Money\Money as MoneyPhp;

/**
 * Casts a money column to Laranex\LaravelMoney\Money.
 *
 * Build it with AsMoney (AsMoney::of('USD'), AsMoney::decimal(),
 * AsMoney::currencyColumn('currency')) or Money::class. Using MoneyCast
 * directly still works: MoneyCast::class (integer minor units, default
 * currency) or MoneyCast::class.':MMK'.
 *
 * Assign a Money instance (ours or moneyphp's), a decimal string such as
 * "12.50", or null. Integers and floats are rejected because "1050" could
 * mean 1050.00 or 10.50.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * Always rebuild the Money from the stored value, so assigning a
     * moneyphp Money or a decimal string reads back as our Money.
     */
    public bool $withoutObjectCaching = true;

    private readonly ?string $currency;

    private readonly ?string $currencyColumn;

    public function __construct(
        ?string $currency = null,
        private readonly bool $decimal = false,
        ?string $currencyColumn = null,
    ) {
        $this->currency = $currency === null || trim($currency) === '' ? null : strtoupper(trim($currency));
        $this->currencyColumn = $currencyColumn === null || trim($currencyColumn) === '' ? null : trim($currencyColumn);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $this->currency($attributes);

        if (! $this->decimal) {
            if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
                throw InvalidMoneyException::invalidStoredAmount($key, $value);
            }

            return Money::ofMinor($value, $currency);
        }

        if (is_float($value)) {
            return Money::of($this->floatToDecimal($key, $value, $currency), $currency);
        }

        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1)) {
            throw InvalidMoneyException::invalidStoredDecimal($key, $value);
        }

        return Money::of($value, $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $columnCurrency = $this->columnCurrency($attributes);

        $money = match (true) {
            $value instanceof Money => $value,
            $value instanceof MoneyPhp => Money::fromMoneyPhp($value),
            is_string($value) => Money::of($value, $this->currency($attributes)),
            default => throw InvalidMoneyException::notMoney($key, $value),
        };

        if ($this->currencyColumn !== null && $columnCurrency !== null && $columnCurrency !== $money->currency()->getCode()) {
            throw CurrencyMismatchException::forCurrencyColumn($key, $this->currencyColumn, $columnCurrency, $money);
        }

        if ($this->currencyColumn === null && ! $money->isSameCurrency(Money::zero($this->currency($attributes)))) {
            throw CurrencyMismatchException::forAttribute($key, $this->currency($attributes)->getCode(), $money);
        }

        $stored = [$key => $this->decimal ? $money->toDecimal() : $money->amount()];

        if ($this->currencyColumn !== null && $columnCurrency === null) {
            $stored[$this->currencyColumn] = $money->currency()->getCode();
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value instanceof Money ? $value->toArray() : null;
    }

    /**
     * The currency this cast reads and writes for a row: the currency
     * column when set, else the cast's fixed currency, else the default.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function currency(array $attributes = []): Currency
    {
        return Container::getInstance()->make(LaravelMoney::class)->currency($this->columnCurrency($attributes) ?? $this->currency);
    }

    /**
     * Whether the column stores a DECIMAL amount rather than integer minor units.
     */
    public function storesDecimal(): bool
    {
        return $this->decimal;
    }

    /**
     * The attribute that holds each row's currency, if any.
     */
    public function currencyColumn(): ?string
    {
        return $this->currencyColumn;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function columnCurrency(array $attributes): ?string
    {
        if ($this->currencyColumn === null) {
            return null;
        }

        $code = $attributes[$this->currencyColumn] ?? null;

        return is_string($code) && trim($code) !== '' ? strtoupper(trim($code)) : null;
    }

    /**
     * Read a float from drivers that return DECIMAL columns as REAL
     * (SQLite), but only when it maps back to an exact decimal.
     */
    private function floatToDecimal(string $key, float $value, Currency $currency): string
    {
        $precision = Container::getInstance()->make(LaravelMoney::class)->precision($currency);
        $decimal = sprintf('%.'.$precision.'F', $value);

        if ((float) $decimal !== $value || abs($value) * (float) Decimal::pow10($precision) >= 2 ** 53) {
            throw InvalidMoneyException::impreciseStoredFloat($key, $value);
        }

        return $decimal;
    }
}
