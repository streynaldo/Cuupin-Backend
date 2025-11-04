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
        'status',
        'bakery_id',
        'discount_id',
    ];

    protected $casts = [
        'price'           => 'integer',
        'discount_price'  => 'integer',
        'status'          => 'string',
        'bakery_id'       => 'integer',
        'discount_id'     => 'integer',
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

    public function applyDiscountPercent(int $percent): bool
    {
        $percent = max(0, min(100, $percent)); // clamp 0..100
        // pakai floor biar tidak overcharge saat pembulatan
        $new = (int) floor($this->price * (100 - $percent) / 100);

        // Hindari write kalau sama (hemat I/O)
        if ($this->discount_price === $new) {
            return false;
        }

        $this->discount_price = $new;
        // gunakan saveQuietly agar tidak memicu events berat (opsional)
        return $this->saveQuietly();
    }

    public function clearDiscount(): void
    {
        $this->discount_id = null;
        $this->discount_price = null;
        $this->save();
    }
}
