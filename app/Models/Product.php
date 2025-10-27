<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_name',
        'description',
        'price',
        'best_before',
        'image_url',
        'discount_price',
        'bakery_id',
        'discount_id',
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
    public function discountEvent()
    {
        return $this->belongsTo(DiscountEvent::class, 'discount_id')->withDefault();
    }
}
