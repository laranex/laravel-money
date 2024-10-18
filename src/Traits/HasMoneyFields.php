<?php

namespace Laranex\LaravelMoney\Traits;

use Laranex\LaravelMoney\Exceptions\InvalidMoneyInstanceException;
use Money\Currency;
use Money\Money;

trait HasMoneyFields
{
    public function getAttribute($key)
    {
        if (in_array($key, $this->getMoneyFields())) {

            return new Money(
                parent::getAttribute($key),
                new Currency('USD')
            );
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value): void
    {
        if (in_array($key, $this->getMoneyFields())) {
            if (! $value instanceof Money) {
                throw new InvalidMoneyInstanceException("The value for '{$key}' must be an instance of Money/Money.");
            }

            parent::setAttribute($key, $value->getAmount());
        } else {
            parent::setAttribute($key, $value);
        }
    }

    public function setMoneyFields(array $moneyFields): void
    {
        $this->moneyFields = $moneyFields;
    }

    public function getMoneyFields(): array
    {
        return $this->moneyFields;
    }
}
