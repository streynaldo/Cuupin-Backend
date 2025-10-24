<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bakery_id',
        'total_price',
        'status',
    ];

    // === Relations ===
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bakery()
    {
        return $this->belongsTo(Bakery::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItems::class);
    }
}
