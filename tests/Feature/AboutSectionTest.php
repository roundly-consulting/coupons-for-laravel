<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Coupons' section carries no credentials, and the risk it does carry is sharper than one:
 * the provider deliberately reports the generated-code alphabet **by size only**, because
 * printing it would hand a brute-forcer the exact key space every coupon code is drawn
 * from. That is a real secret in a package whose codes are bearer tokens for money — and
 * a one-line change to `codeFormat()` would leak it. Live coupon codes must never render
 * either.
 *
 * `mustRender` is required and non-empty, so the negative half can never pass over empty
 * output.
 */
it('renders the coupons section without leaking the code space it guards', function (): void {
    config()->set('coupons.code.charset', 'QWERTYUIOP');
    config()->set('coupons.code.length', 8);

    $coupon = Coupon::factory()->fixed(500)->active()->create(['code' => 'SUMMER42']);

    expect('coupons')->toLeakNoSecrets(
        secrets: [
            // The alphabet is reported by size, never by its symbols. Printing it would
            // give away the exact key space generated codes are drawn from.
            'QWERTYUIOP',
            // No live coupon code ever renders in an `about` section — a code is a bearer
            // token for a discount.
            $coupon->code,
        ],
        mustRender: [
            'Model',
            'Default currency',
            'Redeemer tracking',
            'Generated codes',
            'Route key',
            // The positive proof that the alphabet line reports rather than sitting
            // silently empty: the size is rendered, which is what makes hiding the
            // symbols meaningful rather than accidental.
            '8 chars from a 10-symbol alphabet',
        ],
    );
});
