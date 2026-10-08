<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 currency code used by the money cast and the LaravelMoney
    | facade whenever no explicit currency is given. A cast may override it
    | per attribute: 'price' => MoneyCast::class.':MMK'.
    |
    */

    'default_currency' => env('MONEY_CURRENCY', 'USD'),

];
