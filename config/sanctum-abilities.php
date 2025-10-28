<?php

return [
    'admin' => ['*'],
    'owner' => [
        'bakeries:read',
        'bakeries:write',
        'products:read',
        'products:write',
        'orders:read',
        'orders:update',
        'orders:manage',
        'wallet:read',
        'wallet:withdraw',
        'discounts:manage',
    ],
    'customer' => [
        'products:read',
        'cart:manage',
        'orders:read',
        'orders:create',
        'profile:read',
        'profile:update',
    ],
    'default' => ['profile:read'],
];
