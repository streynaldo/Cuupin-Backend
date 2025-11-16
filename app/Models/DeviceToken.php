<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * DeviceToken belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
