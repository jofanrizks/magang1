<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagedService extends Model
{
    public const FIXED_CODES = [
        'service_1',
        'service_2',
        'service_3',
        'service_4',
        'service_5',
    ];

    protected $table = 'services';

    protected $fillable = [
        'group_id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function options()
    {
        return $this->hasMany(ServiceOption::class, 'service_id')
            ->orderBy('sort_order');
    }

    public function scopeFixed($query)
    {
        return $query->whereIn('code', self::FIXED_CODES);
    }
}
