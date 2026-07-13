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
        return $this->hasMany(User::class);
    }

    public function groupFiles()
    {
        return $this->hasMany(GroupFile::class);
    }
}