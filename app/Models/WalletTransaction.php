<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bakery_wallet_id',
        'amount',
    ];

    public function wallet()
    {
        return $this->belongsTo(BakeryWallet::class, 'bakery_wallet_id');
    }
}
