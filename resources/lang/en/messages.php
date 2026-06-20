<?php

declare(strict_types=1);

return [
    'not_found' => 'The coupon ":code" does not exist.',
    'currency_mismatch' => 'This coupon cannot be used in the selected currency.',
    'minimum_spend_not_met' => 'Your total does not meet this coupon’s minimum spend.',
    'expired' => 'This coupon is no longer available.',
    'at_max_usage' => 'This coupon has reached its usage limit.',
    'already_redeemed' => 'You have already used this coupon.',

    'type' => [
        'fixed' => [
            'label' => 'Fixed amount',
            'description' => 'Subtracts a fixed amount from the price.',
        ],
        'percentage' => [
            'label' => 'Percentage',
            'description' => 'Subtracts a percentage of the price.',
        ],
        'free_shipping' => [
            'label' => 'Free shipping',
            'description' => 'Marks the order for free shipping.',
        ],
    ],
];
