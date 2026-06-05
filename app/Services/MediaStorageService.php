<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central media storage — Laravel Cloud object storage (S3/R2) when configured.
 */
class MediaStorageService
{
    public static function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    public static function usesCloudStorage(): bool
    {
        if (self::diskName() !== 's3') {
            return false;
        }

        $bucket = config('filesystems.disks.s3.bucket');
        $key = config('filesystems.disks.s3.key');

        return !empty($bucket) && !empty($key);
    }

    public static function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $storedName = null
    ): string {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = $storedName ?? (Str::uuid()->toString() . '.' . strtolower($extension));

        $path = $file->storeAs($directory, $filename, [
            'disk' => self::diskName(),
            'visibility' => 'public',
        ]);

        if (!$path) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        return self::normalizeStoredPath($path) ?? $path;
    }

    public static function putContents(string $path, string $contents): string
    {
        $normalized = self::normalizeStoredPath($path) ?? ltrim($path, '/');
        Storage::disk(self::diskName())->put($normalized, $contents, 'public');

        return $normalized;
    }

    /**
     * Store only the relative object key in the database.
     */
    public static function normalizeStoredPath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $value = trim(str_replace('\\', '/', $path));

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            foreach (['/api/v1/storage/', '/storage/'] as $marker) {
                $pos = strpos($value, $marker);
                if ($pos !== false) {
                    return ltrim(substr($value, $pos + strlen($marker)), '/');
                }
            }

            $cloudBase = rtrim((string) config('filesystems.disks.s3.url'), '/');
            if ($cloudBase !== '' && str_starts_with($value, $cloudBase . '/')) {
                return ltrim(substr($value, strlen($cloudBase) + 1), '/');
            }

            return $value;
        }

        return ltrim($value, '/');
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $relative = self::normalizeStoredPath($path);
        if ($relative === null || $relative === '') {
            return null;
        }

        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return self::rewriteLegacyApiStorageUrl($relative);
        }

        if (self::usesCloudStorage()) {
            return Storage::disk('s3')->url($relative);
        }

        return url('/api/v1/storage/' . $relative);
    }

    public static function rewriteLegacyApiStorageUrl(string $url): string
    {
        $relative = self::normalizeStoredPath($url);
        if ($relative === null) {
            return $url;
        }

        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return $relative;
        }

        return self::publicUrl($relative) ?? $url;
    }

    public static function exists(?string $path): bool
    {
        $relative = self::normalizeStoredPath($path);
        if ($relative === null || $relative === '') {
            return false;
        }

        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return true;
        }

        foreach (['s3', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                return true;
            }
        }

        return false;
    }

    public static function deleteIfExists(?string $path): void
    {
        $relative = self::normalizeStoredPath($path);
        if ($relative === null || $relative === '') {
            return;
        }

        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return;
        }

        foreach (['public', 's3'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                Storage::disk($disk)->delete($relative);
            }
        }
    }
}
