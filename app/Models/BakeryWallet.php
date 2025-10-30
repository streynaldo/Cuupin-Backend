<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BakeryWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'bakery_id',
        'total_wallet',
        'total_earned',
        'total_withdrawn',
        'no_rekening', // kalau ada
    ];

    public function bakery()
    {
        return $this->belongsTo(Bakery::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
