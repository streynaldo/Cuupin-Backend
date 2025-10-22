<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'external_id',
        'charge_id',
        'channel_code',
        'amount',
        'currency',
        'status',
        'items',
        'metadata',
        'raw',
    ];

    protected $casts = [
        'items' => 'array',
        'metadata' => 'array',
        'raw' => 'array',
    ];
}
