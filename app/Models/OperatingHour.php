<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_the_week',
        'open_time',
        'close_time',
        'is_closed',
        'bakery_id',
    ];

    // === RELATIONS ===
    public function bakery()
    {
        return $this->belongsTo(Bakery::class);
    }
}
