<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class PhotoStorageService
{
    /**
     * Store uploaded photo: resize/compress per category, save under hashed filename. Returns only filename for DB.
     * Folder (e.g. image/containers/) is fixed in code and not stored.
     *
     * @param  array{max_width?: int, max_height?: int, quality?: int, extension?: string}  $options  Override config category options
     * @return string Filename only to store in DB (e.g. abc123....jpg)
     */
    public function store(UploadedFile $file, string $category, array $options = []): string
    {
        $config = config('photos', []);
        $basePath = rtrim($config['base_path'] ?? 'image', '/');
        $disk = $config['disk'] ?? 'public';
        $categoryConfig = $config['categories'][$category] ?? [];
        $opts = array_merge([
            'max_width' => 1200,
            'max_height' => 1200,
            'quality' => 85,
            'extension' => 'jpg',
        ], $categoryConfig, $options);

        $dir = $basePath.'/'.$category;
        Storage::disk($disk)->makeDirectory($dir);

        $filename = $this->hashedFilename($opts['extension']);
        $fullPath = Storage::disk($disk)->path($dir.'/'.$filename);

        Image::read($file->getRealPath())
            ->scaleDown($opts['max_width'], $opts['max_height'])
            ->toJpeg($opts['quality'])
            ->save($fullPath);

        return $filename;
    }

    /**
     * Generate a unique hashed filename to avoid collisions (e.g. 32 hex chars + extension).
     */
    public function hashedFilename(string $extension = 'jpg'): string
    {
        $ext = strtolower(ltrim($extension, '.'));
        $hash = bin2hex(random_bytes(16));

        return $hash.'.'.$ext;
    }

    /**
     * Delete photo. $pathOrFilename: filename only (e.g. abc.jpg) or legacy full path (e.g. image/containers/abc.jpg).
     * When filename only, pass $category so the full path is built.
     *
     * @return bool True if file was deleted or did not exist
     */
    public function delete(string $pathOrFilename, ?string $category = null): bool
    {
        $disk = config('photos.disk', 'public');
        $fullPath = $this->fullPath($pathOrFilename, $category);
        if (! Storage::disk($disk)->exists($fullPath)) {
            return true;
        }

        return Storage::disk($disk)->delete($fullPath);
    }

    /**
     * Build full path for display/URL/delete. If $pathOrFilename contains '/', treated as legacy full path; else filename only and $category required.
     */
    public function fullPath(string $pathOrFilename, ?string $category = null): string
    {
        if ($pathOrFilename === '') {
            return '';
        }
        if (str_contains($pathOrFilename, '/')) {
            return $pathOrFilename;
        }
        $basePath = rtrim(config('photos.base_path', 'image'), '/');

        return $basePath.'/'.$category.'/'.$pathOrFilename;
    }

    /**
     * Full URL for a stored photo (filename or legacy full path).
     */
    public function url(string $pathOrFilename, ?string $category = null): string
    {
        if (! $pathOrFilename) {
            return '';
        }
        $fullPath = $this->fullPath($pathOrFilename, $category);

        return asset(ltrim($fullPath, '/'));
    }
}
