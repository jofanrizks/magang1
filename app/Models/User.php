<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
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
        'tglapproval',
        'tglupdate',
        'tgldisabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'tgldaftar' => 'datetime',
        'tglapproval' => 'datetime',
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
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}   