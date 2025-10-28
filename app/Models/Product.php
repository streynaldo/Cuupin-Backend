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

    // helper methods discount price
    public function applyDiscount(DiscountEvent $event): void
    {
        $this->discount_id = $event->id;
        $this->discount_price = (int) round($this->price * (100 - $event->discount) / 100);
        $this->save();
    }

    public function clearDiscount(): void
    {
        $this->discount_id = null;
        $this->discount_price = null;
        $this->save();
    }
}
