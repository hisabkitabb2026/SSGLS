<?php

return [
    'enabled' => true,

    'statuses' => [
        'lorry_receipt' => [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],
        'warehouse_item' => [
            'stored' => 'Stored',
            'picked' => 'Picked',
            'loaded' => 'Loaded',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],
    ],

    'features' => [
        'enable_consolidation' => true,
        'enable_load_trips' => true,
        'enable_notifications' => true,
    ],
];
