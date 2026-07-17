<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Tests\Fixtures\SwappedCouponTestCase;
use RoundlyConsulting\Coupons\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different
// base case, and a blanket bind would claim it first. Unit is listed rather than excluded
// because ArchTest.php lives there and `swappableModelsAreNotFinal` reads the
// `coupons.model` config default — so it needs the app booted, and an arch file is not
// automatically test-cased.
uses(TestCase::class)->in('Feature', 'Unit');

// The model-swap proofs need `coupons.model` pointed at the host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedCouponTestCase::class)->in('ModelSwap');
