# Coupons for Laravel

Create, manage, and apply discount coupons in Laravel. Coupons support fixed-amount and
percentage discounts, activation and expiry windows, usage limits, and a native immutable
`Money` value object — with no third-party runtime dependencies.

## Requirements

- PHP 8.3 or 8.4
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/coupons-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="coupons-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="coupons-config"
```

## Configuration

The published `config/coupons.php` exposes a single key:

```php
return [

    // The Eloquent model used to represent a coupon. Swap this for your own
    // subclass of the package's Coupon model if you need to extend behaviour.
    'model' => \RoundlyConsulting\Coupons\Models\Coupon::class,

];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Coupons\Models\Coupon` | Model resolved by `CreateCouponAction`. Point it at your own subclass to extend the coupon. |

The package ships sensible defaults and works with zero configuration.

## Usage

### Money value object

Discounts operate on an immutable `Money` value object — an integer amount in the currency's
minor unit (e.g. cents) plus an ISO 4217 currency code.

```php
use RoundlyConsulting\Coupons\ValueObjects\Money;

$price = new Money(1000, 'EUR'); // €10.00

$price->getAmount();        // 1000
$price->getCurrency();      // 'EUR'
$price->add(new Money(500, 'EUR'));      // €15.00
$price->subtract(new Money(250, 'EUR')); // €7.50 (clamped at zero)
$price->multiply(0.5);                    // €5.00
$price->format('en_US');                  // "€10.00"
```

### Discount types

The `DiscountType` enum decides how a coupon's value is applied to a price:

```php
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\ValueObjects\Money;

// Subtract a fixed amount in minor units.
DiscountType::Fixed->apply(new Money(1000, 'EUR'), 250);      // €7.50

// Subtract a percentage (value is whole percent).
DiscountType::Percentage->apply(new Money(1000, 'EUR'), 25);  // €7.50
```

### Creating coupons

Use `CreateCouponAction` with a `CreateCouponData` DTO. A unique code is generated when you
don't supply one, and a `CouponCreated` event is dispatched.

```php
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;

$coupon = app(CreateCouponAction::class)->execute(
    new CreateCouponData(
        type: DiscountType::Percentage,
        value: 20,           // 20% off
        code: 'SAVE20',      // optional — auto-generated when omitted
        maxUsage: 100,       // optional — 0 means unlimited
    ),
);
```

### Working with a coupon

```php
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

$coupon = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

$coupon->activate();          // activated_at = now (pass a CarbonInterface to schedule)
$coupon->expire($expiresAt);  // expires_at
$coupon->setMaxUsageTo(50);
$coupon->save();

$coupon->isActive();             // bool
$coupon->isExpired();            // bool
$coupon->isAtMaximumUsage();     // bool (a 0 cap is unlimited)
$coupon->hasBeenUsedAtLeastOnce();
$coupon->canBeApplied();         // active, not expired, not at max usage

// Apply the coupon to a price.
$discounted = $coupon->apply(new Money(5000, 'EUR')); // €40.00 for 20% off
```

### Listening for events

The package dispatches `CouponCreated` whenever a coupon is created through the action:

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Events\CouponCreated;

Event::listen(function (CouponCreated $event): void {
    logger()->info('Coupon created', ['code' => $event->coupon->code]);
});
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## Contributing

Contributions are welcome. Please open an issue or pull request on the repository.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
