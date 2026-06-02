<?php

return [

    'rewards' => [
        [
            'id' => 1,
            'title' => '₱50 LesGo Voucher',
            'description' => 'Redeem for ₱50 off your next LesGo delivery booking.',
            'points_cost' => 500,
            'reward_type' => 'voucher',
            'voucher_code' => 'LESGO50',
        ],
        [
            'id' => 2,
            'title' => '₱100 LesEat Voucher',
            'description' => 'Redeem for ₱100 off your next LesEat food order.',
            'points_cost' => 900,
            'reward_type' => 'voucher',
            'voucher_code' => 'EAT100',
        ],
        [
            'id' => 3,
            'title' => 'Free Delivery Pass',
            'description' => 'One free delivery fee waiver on any LesGo or LesEat order.',
            'points_cost' => 750,
            'reward_type' => 'voucher',
            'voucher_code' => 'FREEDEL',
        ],
        [
            'id' => 4,
            'title' => '₱25 LesRide Credit',
            'description' => 'Redeem for ₱25 off your next LesRide booking.',
            'points_cost' => 300,
            'reward_type' => 'voucher',
            'voucher_code' => 'RIDE25',
        ],
    ],

    'referral_points_per_join' => 100,

];
