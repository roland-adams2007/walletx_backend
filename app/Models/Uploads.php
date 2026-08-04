<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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
        $publicId = (string) Str::uuid();
        $isImage = str_starts_with($file->getMimeType(), 'image/');

        if ($isImage) {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->toWebp(quality: 85);

            $extension = 'webp';
            $mimeType = 'image/webp';
            $contents = (string) $encoded;
            $fileSize = strlen($contents);
        } else {
            $extension = $file->getClientOriginalExtension();
            $mimeType = $file->getMimeType();
            $contents = file_get_contents($file->getRealPath());
            $fileSize = $file->getSize();
        }

        $path = "{$folder}/{$publicId}.{$extension}";

        Storage::disk('s3')->put($path, $contents);

        return self::create([
            'file_original_name' => $file->getClientOriginalName(),
            'file_name' => Storage::disk('s3')->url($path),
            'public_id' => $publicId,
            'user_id' => $userId,
            'extension' => $extension,
            'type' => $mimeType,
            'file_size' => $fileSize,
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
