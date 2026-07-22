<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ServiceOption;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GroupFile extends Model
{
    protected $appends = [
        'file_url',
        'file_type',
    ];

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

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getFileTypeAttribute(): string
    {
        if ($this->mime_type === 'application/pdf') {
            return 'pdf';
        }

        if (Str::startsWith((string) $this->mime_type, 'image/')) {
            return 'image';
        }

        return 'other';
    }
}
