<?php

namespace App\Models;
use App\Models\User;
use App\Models\GroupFile;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [

        'name'

    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withTimestamps();
    }

    public function groupFiles()
    {
        return $this->hasMany(GroupFile::class);
    }
}
