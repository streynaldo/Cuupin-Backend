<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bakery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'logo_url',
        'banner_url',
        'address',
        'latitude',
        'longitude',
        'contact_info',
        'discount_status',
        'is_active',
    ];

    protected $casts = [
        'discount_status' => 'string',
        'is_active' => 'boolean',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];
    // === Relations ===
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function events(){
        return $this->hasMany(DiscountEvent::class);
    }

    public function operatinghours(){
        return $this->hasMany(OperatingHour::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wallet()
    {
        return $this->hasOne(BakeryWallet::class);
    }
}
