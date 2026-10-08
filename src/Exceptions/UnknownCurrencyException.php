<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

class UnknownCurrencyException extends MoneyException
{
    public static function code(string $code): self
    {
        return new self(sprintf(
            'Unknown currency [%s]. Use an ISO 4217 code (USD, EUR, JPY, MMK...) or register a custom currency in config/money.php under "currencies", e.g. [\'%s\' => 2].',
            $code,
            $code === '' ? 'PTS' : $code,
        ));
    }

    public static function defaultCurrency(string $code): self
    {
        return new self(sprintf(
            'The default currency [%s] (config "money.default_currency" / env MONEY_CURRENCY) is not a known currency. Use an ISO 4217 code or register it under "money.currencies".',
            $code,
        ));
    }
}
