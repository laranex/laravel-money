<?php

declare(strict_types=1);

use Laranex\LaravelMoney\Facades\LaravelMoney;
use Laranex\LaravelMoney\Formatting\IntlFormatter;
use Laranex\LaravelMoney\Formatting\PlainFormatter;
use Laranex\LaravelMoney\Money;
use Laranex\LaravelMoney\Rounding;

it('formats without intl as CODE decimal', function (): void {
    LaravelMoney::useFormatter(new PlainFormatter);

    expect(Money::of('1234.5', 'USD')->format())->toBe('USD 1234.50')
        ->and(Money::of('-3', 'KWD')->format('de_DE'))->toBe('KWD -3.000')
        ->and((string) Money::of('15', 'JPY'))->toBe('JPY 15');
});

it('picks the intl formatter when the extension is loaded', function (): void {
    expect(LaravelMoney::formatter())->toBeInstanceOf(extension_loaded('intl') ? IntlFormatter::class : PlainFormatter::class);
});

it('formats with the configured locale, then the app locale', function (): void {
    expect(LaravelMoney::locale())->toBe('en');

    app()->setLocale('de_DE');

    expect(LaravelMoney::locale())->toBe('de_DE');

    config()->set('money.locale', 'fr_FR');

    expect(LaravelMoney::locale())->toBe('fr_FR');
});

describe('with intl', function (): void {
    it('formats in the default locale', function (): void {
        expect(Money::of('1234.5', 'USD')->format())->toBe('$1,234.50')
            ->and((string) Money::of('-1234.5', 'USD'))->toBe('-$1,234.50')
            ->and(Money::of('1500', 'JPY')->format())->toBe('¥1,500')
            ->and(Money::of('1.5', 'KWD')->format())->toBe("KWD\u{00A0}1.500")
            ->and(Money::of('300', 'PTS')->format())->toBe("PTS\u{00A0}300");
    });

    it('formats in a given locale', function (): void {
        expect(Money::of('1234.5', 'EUR')->format('de_DE'))->toBe("1.234,50\u{00A0}€")
            ->and(Money::of('1234567.89', 'INR')->format('en_IN'))->toBe('₹12,34,567.89')
            ->and(Money::of('1234.5', 'EUR')->format('fr_FR'))->toBe("1\u{202F}234,50\u{00A0}€")
            ->and(Money::of('12345.5', 'EUR')->format('es_ES'))->toBe("12.345,50\u{00A0}€");
    });

    it('follows the configured locale', function (): void {
        config()->set('money.locale', 'de_DE');

        expect(Money::of('1234.5', 'EUR')->format())->toBe("1.234,50\u{00A0}€");
    });

    it('formats huge amounts exactly, unlike a float', function (): void {
        $money = Money::of('123456789012345678901.23', 'USD');

        expect($money->format())->toBe('$123,456,789,012,345,678,901.23')
            ->and(Money::of('90071992547409.93', 'USD')->format())->toBe('$90,071,992,547,409.93');
    });

    it('matches ICU for every amount a float holds exactly', function (string $locale): void {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        foreach ([['USD', 2], ['JPY', 0], ['KWD', 3], ['MMK', 2], ['EUR', 2]] as [$currency, $precision]) {
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);

            foreach (['0', '1', '-1', '12.34', '-1234.56', '999.99', '1000', '12345.67', '1234567.89', '-98765432.1', '0.05'] as $value) {
                $money = Money::of($value, $currency, Rounding::HalfUp);

                expect($money->format($locale))->toBe($formatter->formatCurrency((float) $money->toDecimal(), $currency));
            }
        }
    })->with(['en_US', 'de_DE', 'fr_FR', 'ja_JP', 'en_IN', 'my_MM', 'ar_EG', 'es_ES', 'de_CH', 'th_TH', 'fa_IR', 'pl_PL']);
})->skip(! extension_loaded('intl'), 'ext-intl is not installed');
