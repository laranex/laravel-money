# Laravel Money

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-money.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-money)
[![Tests](https://github.com/laranex/laravel-money/actions/workflows/tests.yml/badge.svg)](https://github.com/laranex/laravel-money/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-money.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-money)
[![License](https://img.shields.io/packagist/l/laranex/laravel-money.svg?style=flat-square)](LICENSE.md)

Money for Laravel: exact, currency-aware amounts with arithmetic, percentages, allocation, formatting and Eloquent casts. Built for humans and AI agents.

## Why Laravel Money

Money is easy to get subtly wrong: floats drop cents, "divide by 100" breaks for yen and Kuwaiti dinar, and splitting a bill loses a cent. Laravel Money handles all of that for you, for every currency:

- **Precision comes from the currency.** USD and MMK have 2 decimals, JPY 0, KWD 3, all from ISO 4217. You can add your own currencies, such as loyalty points with 0 decimals.
- **No floats, ever.** Amounts are integers in minor units or decimal strings, and all math is exact. Passing a float throws an exception with a hint.
- **Helpers you actually need:** `plus`, `minus`, `times`, `dividedBy`, `percent`, `addPercent`, `subtractPercent`, `percentageOf`, `split`, `allocate`, `roundTo`, `sum`, `avg` and more.
- **Eloquent casts** for integer and DECIMAL columns, with a fixed currency or a currency column per row.
- **Exact formatting** for the locale (`$1,234.50`, `1.234,50 €`), even for amounts too large for a float.

## Installation

```bash
composer require laranex/laravel-money
```

Requirements: PHP ^8.1 with ext-bcmath, Laravel 10 to 13, moneyphp/money ^4.0, and ext-intl (optional, for locale-aware formatting).

## Documentation

The full documentation lives at **[laranex.vercel.app/laravel-money](https://laranex.vercel.app/laravel-money)**:

- [Introduction](https://laranex.vercel.app/laravel-money/introduction): why Laravel Money, requirements and a quick example
- [Installation](https://laranex.vercel.app/laravel-money/installation): installing, setting the default currency and the configuration
- [Usage](https://laranex.vercel.app/laravel-money/usage): creating, comparing, formatting and serializing Money values, and errors
- [Arithmetic](https://laranex.vercel.app/laravel-money/arithmetic): exact math, percentages, splitting, allocation and rounding modes
- [Casts](https://laranex.vercel.app/laravel-money/casts): Eloquent casts for integer and DECIMAL columns
- [AI Agent Skill](https://laranex.vercel.app/laravel-money/ai-agent-skill): the agent skill for Claude Code, Codex, Cursor and other coding agents

## License

MIT. See [LICENSE.md](LICENSE.md).
