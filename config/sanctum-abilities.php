<?php

return [
    'admin' => ['*'],
    'owner' => [
        'bakeries:read',
        'bakeries:write',
        'products:read',
        'products:write',
        'operating-hours:write',
        'orders:read',
        'orders:write',
        'orders:update',
        'orders:manage',
        'wallet:read',
        'wallet:withdraw',
        'discounts:write',
    ],
    'customer' => [
        'products:read',
        'cart:write',
        'orders:read',
        'orders:create',
        'profile:read',
        'profile:update',
    ],
    'default' => ['profile:read'],
];
