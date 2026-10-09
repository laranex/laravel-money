<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laranex\LaravelMoney\Exceptions\InvalidMoneyException;
use Laranex\LaravelMoney\Exceptions\UnknownCurrencyException;
use Laranex\LaravelMoney\Formatting\Formatter;
use Laranex\LaravelMoney\Formatting\IntlFormatter;
use Laranex\LaravelMoney\Formatting\PlainFormatter;
use Money\Currency;
use Money\Money as MoneyPhp;
use NumberFormatter;

/**
 * The service behind the LaravelMoney facade and the money() helper. It
 * reads config/money.php and builds Money instances.
 */
class LaravelMoney
{
    private ?Formatter $formatter = null;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly CurrencyRegistry $currencies,
    ) {}

    /**
     * Money from a decimal amount such as "1234.50" or 1234.
     */
    public function of(int|string|float $amount, Currency|string|null $currency = null, ?Rounding $rounding = null): Money
    {
        return Money::of($amount, $currency, $rounding);
    }

    /**
     * Money from an amount in minor units (cents), such as 123450.
     */
    public function ofMinor(int|string|float $minor, Currency|string|null $currency = null): Money
    {
        return Money::ofMinor($minor, $currency);
    }

    /**
     * Zero in the given or default currency.
     */
    public function zero(Currency|string|null $currency = null): Money
    {
        return Money::zero($currency);
    }

    /**
     * Wrap a moneyphp/money Money object.
     */
    public function fromMoneyPhp(MoneyPhp $money): Money
    {
        return Money::fromMoneyPhp($money);
    }

    /**
     * The currency registry (ISO 4217 plus configured custom currencies).
     */
    public function currencies(): CurrencyRegistry
    {
        return $this->currencies;
    }

    /**
     * Resolve and validate a currency, falling back to the default currency.
     *
     * @throws UnknownCurrencyException
     */
    public function currency(Currency|string|null $currency = null): Currency
    {
        if ($currency === null || $currency === '') {
            return $this->defaultCurrency();
        }

        return $this->currencies->resolve($currency);
    }

    /**
     * The configured default currency (money.default_currency).
     *
     * @throws UnknownCurrencyException
     */
    public function defaultCurrency(): Currency
    {
        $code = $this->config->get('money.default_currency');
        $code = is_string($code) ? strtoupper(trim($code)) : '';
        $code = $code === '' ? 'USD' : $code;

        if (! $this->currencies->has($code)) {
            throw UnknownCurrencyException::defaultCurrency($code);
        }

        return new Currency($code);
    }

    /**
     * The number of decimal places of a currency (default currency when null).
     */
    public function precision(Currency|string|null $currency = null): int
    {
        return $this->currencies->precision($this->currency($currency));
    }

    /**
     * The default rounding mode (money.rounding).
     */
    public function rounding(): Rounding
    {
        $rounding = $this->config->get('money.rounding');

        if ($rounding instanceof Rounding) {
            return $rounding;
        }

        if ($rounding === null) {
            return Rounding::HalfUp;
        }

        $resolved = is_string($rounding) ? Rounding::tryFrom($rounding) : null;

        if ($resolved === null) {
            throw InvalidMoneyException::invalidConfig('rounding', 'one of '.implode(', ', array_map(static fn (Rounding $case): string => '"'.$case->value.'"', Rounding::cases())));
        }

        return $resolved;
    }

    /**
     * The locale used to format money (money.locale, else the app locale).
     */
    public function locale(): string
    {
        $locale = $this->config->get('money.locale') ?? $this->config->get('app.locale');

        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }

    /**
     * Format money for display in the given or configured locale.
     */
    public function format(Money $money, ?string $locale = null): string
    {
        return $this->formatter()->format($money->toDecimal(), $money->currency()->getCode(), $money->precision(), $locale ?? $this->locale());
    }

    /**
     * The serialization options (money.serialization).
     *
     * @return array{amount: 'minor'|'decimal', include_decimal: bool, include_formatted: bool}
     */
    public function serialization(): array
    {
        $options = $this->config->get('money.serialization');
        $options = is_array($options) ? $options : [];
        $amount = $options['amount'] ?? 'minor';

        if ($amount !== 'minor' && $amount !== 'decimal') {
            throw InvalidMoneyException::invalidConfig('serialization.amount', '"minor" or "decimal"');
        }

        return [
            'amount' => $amount,
            'include_decimal' => (bool) ($options['include_decimal'] ?? true),
            'include_formatted' => (bool) ($options['include_formatted'] ?? true),
        ];
    }

    /**
     * The formatter in use: exact intl formatting when ext-intl is
     * installed, "{CODE} {decimal}" otherwise.
     */
    public function formatter(): Formatter
    {
        return $this->formatter ??= class_exists(NumberFormatter::class) ? new IntlFormatter : new PlainFormatter;
    }

    /**
     * Replace the formatter, e.g. to force plain formatting.
     */
    public function useFormatter(?Formatter $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }
}
