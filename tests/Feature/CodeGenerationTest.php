<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Exceptions\CouponException;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Money\Money;

/**
 * `coupons.code.length` / `coupons.code.charset` used to be read only by the `about`
 * section: every generated code was 6 characters of an uppercased `Str::random()`,
 * whatever the host configured. These pin that generation really honours both keys.
 */
function generatedCode(): string
{
    return Coupons::create(CreateCouponData::percentage(10))->code;
}

it('generates codes of the configured length from the configured alphabet', function (): void {
    config()->set('coupons.code.length', 12);
    config()->set('coupons.code.charset', 'XYZ');

    foreach (range(1, 5) as $ignored) {
        expect(generatedCode())->toMatch('/^[XYZ]{12}$/');
    }
});

it('keeps the shipped format when the keys are left at their defaults', function (): void {
    expect(generatedCode())->toMatch('/^[A-Z0-9]{6}$/');
});

// Codes are case-insensitive and stored upper-cased, so a lowercase alphabet yields the same
// codes its upper-case twin would — never a code no lookup could match.
it('upper-cases a lowercase alphabet, since codes are case-insensitive', function (): void {
    config()->set('coupons.code.charset', 'abcdefgh');

    expect(generatedCode())->toMatch('/^[A-H]{6}$/');
});

it('rejects an alphabet whose symbols differ only by case', function (): void {
    config()->set('coupons.code.charset', 'abcA');

    expect(fn () => generatedCode())->toThrow(InvalidCouponConfiguration::class, 'duplicate symbols (case-insensitively)');
});

it('draws from a multibyte alphabet by symbol, not by byte', function (): void {
    config()->set('coupons.code.length', 8);
    config()->set('coupons.code.charset', 'ÄÖÜ');

    $code = generatedCode();

    expect(mb_strlen($code))->toBe(8)
        ->and($code)->toMatch('/^[ÄÖÜ]{8}$/u');
});

it('reads the length from an env-style numeric string', function (): void {
    config()->set('coupons.code.length', '9');

    expect(generatedCode())->toMatch('/^[A-Z0-9]{9}$/');
});

it('falls back to the shipped format when the keys are blank', function (string $blank): void {
    config()->set('coupons.code.length', $blank);
    config()->set('coupons.code.charset', $blank);

    expect(CodeFormat::length())->toBe(6)
        ->and(implode('', CodeFormat::symbols()))->toBe(CodeFormat::DEFAULT_CHARSET)
        ->and(generatedCode())->toMatch('/^[A-Z0-9]{6}$/');
})->with(['empty' => '', 'whitespace' => '  ']);

it('falls back to the shipped format when the keys are absent', function (): void {
    config()->set('coupons.code', null);

    expect(CodeFormat::length())->toBe(6)
        ->and(implode('', CodeFormat::symbols()))->toBe(CodeFormat::DEFAULT_CHARSET)
        ->and(generatedCode())->toMatch('/^[A-Z0-9]{6}$/');
});

it('accepts the length bounds', function (int $length): void {
    config()->set('coupons.code.length', $length);

    expect(mb_strlen(generatedCode()))->toBe($length);
})->with([4, 64]);

it('rejects a length outside the sane bounds', function (mixed $length): void {
    config()->set('coupons.code.length', $length);

    expect(fn () => generatedCode())->toThrow(InvalidCouponConfiguration::class, 'coupons.code.length');
    expect(Coupon::query()->count())->toBe(0);
})->with([
    'too short' => 3,
    'too long' => 65,
    'zero' => 0,
    'not a number' => 'six',
]);

it('rejects an unusable alphabet', function (mixed $charset, string $reason): void {
    config()->set('coupons.code.charset', $charset);

    expect(fn () => generatedCode())->toThrow(InvalidCouponConfiguration::class, $reason);
    expect(Coupon::query()->count())->toBe(0);
})->with([
    'not a string' => [['A', 'B'], 'must be a string'],
    'a space' => ['ABC DEF', 'whitespace or control'],
    'a tab' => ["ABC\tDEF", 'whitespace or control'],
    'a newline' => ["ABCDEF\n", 'whitespace or control'],
    'a control character' => ["ABC\x07DEF", 'whitespace or control'],
    'invalid utf-8' => ["AB\xFFCD", 'whitespace or control'],
    'a duplicate' => ['ABCA', 'duplicate'],
    'a single symbol' => ['A', 'at least 2'],
]);

it('never echoes the configured alphabet in its errors', function (): void {
    config()->set('coupons.code.charset', 'SECRETALPHABT');

    expect(fn () => generatedCode())->toThrow(function (InvalidCouponConfiguration $e): void {
        expect($e->getMessage())->not->toContain('SECRET');
    });
});

it('is a coupon exception so a host catches it with the rest', function (): void {
    expect(InvalidCouponConfiguration::invalidCharset('duplicate symbols'))->toBeInstanceOf(CouponException::class);
});

it('leaves explicit codes alone whatever the format says', function (): void {
    config()->set('coupons.code.length', 4);
    config()->set('coupons.code.charset', 'XY');

    expect(Coupons::create(CreateCouponData::percentage(10, code: 'SUMMER-2026'))->code)->toBe('SUMMER-2026');
});

it('retries a code that is already taken', function (): void {
    Event::fake(CouponCreated::class);

    // Same seed per resolution: the action's first attempt reproduces $taken exactly.
    app()->bind(Randomizer::class, static fn (): Randomizer => new Randomizer(new Mt19937(42)));

    $taken = CodeFormat::generate(app(Randomizer::class));
    Coupon::factory()->create(['code' => $taken]);

    $coupon = app(CreateCouponAction::class)->execute(CreateCouponData::fixed(Money::ofMinor(100, 'EUR')));

    expect($coupon->code)->not->toBe($taken)
        ->toMatch('/^[A-Z0-9]{6}$/');
});

it('treats a soft-deleted coupon code as taken', function (): void {
    app()->bind(Randomizer::class, static fn (): Randomizer => new Randomizer(new Mt19937(7)));

    $taken = CodeFormat::generate(app(Randomizer::class));
    Coupon::factory()->create(['code' => $taken])->delete();

    // A trashed coupon's code is free for an explicit reuse, but a generated code never picks
    // it: a fresh code should not collide with a pruned coupon's redemption history.
    expect(generatedCode())->not->toBe($taken);
});

it('gives up with a clear error once the code space is exhausted', function (): void {
    config()->set('coupons.code.length', 4);
    config()->set('coupons.code.charset', 'AB');

    foreach (['A', 'B'] as $a) {
        foreach (['A', 'B'] as $b) {
            foreach (['A', 'B'] as $c) {
                foreach (['A', 'B'] as $d) {
                    Coupon::factory()->create(['code' => $a.$b.$c.$d]);
                }
            }
        }
    }

    expect(fn () => generatedCode())->toThrow(InvalidCouponConfiguration::class, 'unique coupon code');
});

it('resolves a cryptographically secure randomizer by default', function (): void {
    expect(app(Randomizer::class)->engine)->toBeInstanceOf(Secure::class);
});
