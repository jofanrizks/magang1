<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'activity',
        'description',
        'ip_address',
        'browser',
        'operating_system',
        'device_type',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
