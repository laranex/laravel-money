<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The currency code used by Money::of(), money(), the facade and the
    | casts whenever no currency is given. It must be an ISO 4217 code or
    | one of the custom currencies below.
    |
    */

    'default_currency' => env('MONEY_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Default Rounding
    |--------------------------------------------------------------------------
    |
    | How results that fall between two minor units are rounded by times(),
    | dividedBy(), percent(), split ratios and friends. Every method also
    | takes a Laranex\LaravelMoney\Rounding case to override it per call.
    |
    | Supported: "half_up", "half_down", "half_even", "half_odd",
    |            "half_positive_infinity", "half_negative_infinity",
    |            "ceiling", "floor"
    |
    */

    'rounding' => 'half_up',

    /*
    |--------------------------------------------------------------------------
    | Custom Currencies
    |--------------------------------------------------------------------------
    |
    | Extra currency codes and their number of decimal places, e.g. loyalty
    | points: ['PTS' => 0]. Entries here take precedence over ISO 4217, so
    | they can also override the precision of an ISO currency.
    |
    */

    'currencies' => [
        // 'PTS' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting Locale
    |--------------------------------------------------------------------------
    |
    | The locale used by format() and the "formatted" serialization key.
    | Null uses the application locale (config "app.locale").
    |
    */

    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    |
    | The shape of toArray() / JSON and of serialized model attributes.
    | Amounts are always strings.
    |
    | amount:            "minor" ("123450") or "decimal" ("1234.50")
    | include_decimal:   add a "decimal" key when amount is "minor"
    | include_formatted: add a "formatted" key ("$1,234.50")
    |
    */

    'serialization' => [
        'amount' => 'minor',
        'include_decimal' => true,
        'include_formatted' => true,
    ],

];
