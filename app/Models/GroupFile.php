<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ServiceOption;

class GroupFile extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
        'service_option_id',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function serviceOption()
    {
        return $this->belongsTo(ServiceOption::class);
    }
}
