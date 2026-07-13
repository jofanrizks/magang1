<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupFile extends Model
{
    protected $fillable = [
        'user_id',
        'group_id',
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
}