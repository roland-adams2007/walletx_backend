<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Uploads extends Model
{
    protected $fillable = [
        'file_original_name',
        'file_name',
        'public_id',
        'user_id',
        'extension',
        'type',
        'file_size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->file_name);
    }
}
