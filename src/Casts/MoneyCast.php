<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Casts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\LaravelMoney;
use Money\Currency;
use Money\Money;

/**
 * Casts an integer column holding minor units (cents) to a Money object.
 *
 * Usage: 'price' => MoneyCast::class (default currency) or
 * 'price' => MoneyCast::class.':MMK' (per-attribute currency).
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(
        private readonly ?string $currency = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! (is_string($value) && is_numeric($value) && preg_match('/^-?[0-9]+$/', $value) === 1)) {
            throw InvalidMoneyException::invalidStoredAmount($key, $value);
        }

        return new Money($value, $this->currency());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Money) {
            throw InvalidMoneyException::notMoney($key, $value);
        }

        $currency = $this->currency();

        if (! $value->getCurrency()->equals($currency)) {
            throw InvalidMoneyException::currencyMismatch($key, $currency->getCode(), $value->getCurrency()->getCode());
        }

        return $value->getAmount();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{amount: string, currency: string}|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (! $value instanceof Money) {
            return null;
        }

        return $value->jsonSerialize();
    }

    /**
     * The currency this cast stores and returns.
     */
    public function currency(): Currency
    {
        return Container::getInstance()->make(LaravelMoney::class)->currency($this->currency);
    }
}
