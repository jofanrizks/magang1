<?php

namespace App\Models;

// Laravel Auth base
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    protected $table = 'users';

    protected $fillable = [
        'role',
        'nik',
        'nama',
        'instansi',
        'jabatan',
        'telp',
        'password',
        'sts',
        'approval',
        'tgldaftar',
        'tglupdate',
        'tgldisabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'tgldaftar' => 'datetime',
        'tglupdate' => 'datetime',
        'tgldisabled' => 'datetime',
    ];

    public function verificationCodes()
    {
        return $this->hasMany(VerificationCode::class);
    }


    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeUser($query)
    {
        return $query->where('role', 'user');
    }

    public function scopePending($query)
    {
        return $query->where('approval', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('sts', 'aktif');
    }
}