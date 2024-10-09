<?php

namespace Laranex\LaravelMoney\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laranex\LaravelMoney\LaravelMoney
 */
class LaravelMoney extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laranex\LaravelMoney\LaravelMoney::class;
    }
}
