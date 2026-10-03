<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/coupons-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel">
    <img src="art/hero.png" alt="Coupons for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/coupons-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/coupons-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/coupons-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/coupons-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/coupons-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/coupons-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Coupons for Laravel

Create, validate and redeem discount coupons in Laravel: fixed, percentage (optionally capped)
and free-shipping discounts with currency locks, minimum spends, activation windows and global
or per-customer usage limits. Redemption is atomic, and every amount is an exact
[money-for-laravel](https://github.com/roundly-consulting/money-for-laravel) `Money`.

## Installation

Requires PHP 8.4 (`ext-bcmath`), Laravel 12 or 13, and MySQL/MariaDB, PostgreSQL or SQLite.

```bash
composer require roundly-consulting/coupons-for-laravel
php artisan vendor:publish --tag="coupons-migrations"
php artisan migrate
```

If the models that redeem coupons have UUID/ULID keys, set `COUPONS_KEY_TYPE` **before**
migrating.

## Usage

Create a coupon through the facade. New coupons start inactive, so activate it:

```php
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Facades\Coupons;

$coupon = Coupons::generate(DiscountType::Percentage, value: 2000, code: 'SUMMER', maxUsage: 100); // 20 % off
$coupon->activate()->save();
```

At checkout, check it, preview the discount and redeem it against the cart total:

```php
use RoundlyConsulting\Money\Money;

$cart = Money::ofMinor(5000, 'EUR');                       // €50.00

Coupons::code('SUMMER')->check($cart, $user);              // null, or why not: Expired, AtMaxUsage, …
Coupons::code('SUMMER')->preview($cart);                   // 10.00 EUR, without redeeming

$result = Coupons::code('SUMMER')->redeem($cart, $user);   // atomic; throws a CouponException if refused

$result->discount;                                         // 10.00 EUR
$result->total;                                            // 40.00 EUR
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/coupons-for-laravel](https://roundly-consulting.com/open-source/docs/coupons-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
