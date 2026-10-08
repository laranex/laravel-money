<?php

declare(strict_types=1);

namespace Laranex\LaravelMoney\Exceptions;

use InvalidArgumentException;

/**
 * Base class for every exception thrown by laranex/laravel-money.
 */
abstract class MoneyException extends InvalidArgumentException {}
