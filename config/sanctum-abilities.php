<?php

// atur permission abilities berdasarkan peran pengguna
return [
    'admin' => ['*'],

    'owner' => [
        'products:read','products:write',
        'orders:read','orders:manage',
        'wallet:read','wallet:withdraw',
        'discounts:manage',
    ],

    'customer' => [
        'products:read',
        'cart:manage',
        'orders:read','orders:create',
        'profile:read','profile:update',
    ],

    'default' => ['profile:read'],
];
