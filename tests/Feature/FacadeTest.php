<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Facades\Coupons;

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Coupons::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
