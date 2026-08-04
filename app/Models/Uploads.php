<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public static function upload(UploadedFile $file, int $userId, string $folder = 'uploads'): self
    {
        $extension = $file->getClientOriginalExtension();
        $publicId = (string) Str::uuid();
        $path = "{$folder}/{$publicId}.{$extension}";

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        return self::create([
            'file_original_name' => $file->getClientOriginalName(),
            'file_name' => Storage::disk('s3')->url($path),
            'public_id' => $publicId,
            'user_id' => $userId,
            'extension' => $extension,
            'type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function delete()
    {
        Storage::disk('s3')->delete($this->getPathFromUrl());
        return parent::delete();
    }

    protected function getPathFromUrl(): string
    {
        $baseUrl = rtrim(Storage::disk('s3')->url(''), '/');
        return ltrim(Str::after($this->file_name, $baseUrl), '/');
    }
}
