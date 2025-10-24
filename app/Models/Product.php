<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'bakery_id',
        'name',
        'category',
        'price',
        'stock',
    ];

    // === Relations ===
    public function bakery()
    {
        return $this->belongsTo(Bakery::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class);
    }
}
