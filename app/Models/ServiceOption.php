<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOption extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(ManagedService::class, 'service_id');
    }
}
