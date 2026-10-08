<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Money\Currencies;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\CurrencyList;
use Money\Currencies\ISOCurrencies;
use Money\Currency;

/**
 * Every currency the application knows, with its precision (decimal places).
 *
 * ISO 4217 currencies come from moneyphp's ISOCurrencies (USD 2, JPY 0,
 * KWD 3, MMK 2...). Custom currencies from config/money.php are checked
 * first, so they can add new codes or override an ISO precision.
 */
class CurrencyRegistry
{
    private readonly Currencies $currencies;

    /**
     * @var array<non-empty-string, non-negative-int>
     */
    private readonly array $custom;

    /**
     * @param  array<mixed>  $custom  currency code => decimal places, e.g. ['PTS' => 0]
     */
    public function __construct(array $custom = [])
    {
        $list = [];

        foreach ($custom as $code => $precision) {
            $code = is_string($code) ? strtoupper(trim($code)) : '';

            if ($code === '' || ! is_int($precision) || $precision < 0) {
                throw InvalidMoneyException::invalidConfig('currencies', 'a map of currency codes to non-negative integer decimal places, e.g. [\'PTS\' => 0]');
            }

            $list[$code] = $precision;
        }

        $this->custom = $list;
        $this->currencies = new AggregateCurrencies([new CurrencyList($list), new ISOCurrencies]);
    }

    /**
     * Whether the currency is an ISO 4217 code or a configured custom currency.
     */
    public function has(Currency|string $currency): bool
    {
        $code = $currency instanceof Currency ? $currency->getCode() : strtoupper(trim($currency));

        return $code !== '' && $this->currencies->contains(new Currency($code));
    }

    /**
     * The currency for a code, validated against the registry.
     *
     * @throws UnknownCurrencyException
     */
    public function resolve(Currency|string $currency): Currency
    {
        $code = $currency instanceof Currency ? $currency->getCode() : strtoupper(trim($currency));

        if ($code === '' || ! $this->has($code)) {
            throw UnknownCurrencyException::code($code);
        }

        return new Currency($code);
    }

    /**
     * The number of decimal places (minor-unit digits) of a currency.
     *
     * @throws UnknownCurrencyException
     */
    public function precision(Currency|string $currency): int
    {
        return $this->currencies->subunitFor($this->resolve($currency));
    }

    /**
     * The configured custom currencies.
     *
     * @return array<non-empty-string, non-negative-int>
     */
    public function custom(): array
    {
        return $this->custom;
    }

    /**
     * The registry as a moneyphp Currencies instance, for moneyphp
     * formatters, parsers and converters.
     */
    public function currencies(): Currencies
    {
        return $this->currencies;
    }
}
