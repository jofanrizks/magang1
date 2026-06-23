<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'type',
        'is_used',
        'expired_at'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}