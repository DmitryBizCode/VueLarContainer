<?php

return [
    'status_options' => ['available', 'in_use', 'maintenance', 'out_of_service', 'in_transit', 'reserved'],

    'type_presets' => [
        '20ft' => [
            'width' => 2.35,
            'length' => 5.90,
            'height' => 2.39,
            'max_weight' => 28120,
        ],
        '40ft' => [
            'width' => 2.35,
            'length' => 12.03,
            'height' => 2.39,
            'max_weight' => 28700,
        ],
        '40ft_high_cube' => [
            'width' => 2.35,
            'length' => 12.03,
            'height' => 2.70,
            'max_weight' => 28700,
        ],
        '45ft' => [
            'width' => 2.35,
            'length' => 13.56,
            'height' => 2.70,
            'max_weight' => 29000,
        ],
    ],
];
