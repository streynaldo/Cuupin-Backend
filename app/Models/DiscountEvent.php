<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class DiscountEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_name',
        'discount',
        'discount_photo',
        'discount_start_time',
        'discount_end_time',
    ];

    protected $casts = [
        'discount_start_time' => 'datetime',
        'discount_end_time'   => 'datetime',
    ];

    // === RELATIONS ===
    public function products()
    {
        return $this->hasMany(Product::class, 'discount_id');
    }

    // === SCOPES & METHODS ===
    public function scopeActive($query, ?Carbon $at = null)
    {
        $at = $at ?: now();
        return $query->where('discount_start_time', '<=', $at)
            ->where('discount_end_time', '>=', $at);
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at = $at ?: now();
        return ($this->discount_start_time && $this->discount_end_time)
            && $this->discount_start_time->lte($at)
            && $this->discount_end_time->gte($at);
    }
}
