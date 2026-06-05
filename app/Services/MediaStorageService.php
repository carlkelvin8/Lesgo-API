<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Central media storage — Laravel Cloud object storage (S3/R2) when configured.
 *
 * Set on Laravel Cloud:
 *   AWS_BUCKET, AWS_DEFAULT_REGION=auto, AWS_ENDPOINT, AWS_URL,
 *   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, MEDIA_DISK=s3
 */
class MediaStorageService
{
    public static function diskName(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    public static function usesCloudStorage(): bool
    {
        return self::diskName() === 's3' && !empty(config('filesystems.disks.s3.bucket'));
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

        return $path;
    }

    public static function putContents(string $path, string $contents): string
    {
        $normalized = ltrim($path, '/');
        Storage::disk(self::diskName())->put($normalized, $contents, 'public');

        return $normalized;
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $value = trim($path);

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return self::rewriteLegacyApiStorageUrl($value);
        }

        $relative = ltrim($value, '/');

        if (self::usesCloudStorage()) {
            return Storage::disk('s3')->url($relative);
        }

        return url('/api/v1/storage/' . $relative);
    }

    /**
     * Rewrite old /api/v1/storage/... links to the cloud CDN URL when possible.
     */
    public static function rewriteLegacyApiStorageUrl(string $url): string
    {
        if (!self::usesCloudStorage()) {
            return $url;
        }

        $marker = '/api/v1/storage/';
        $pos = strpos($url, $marker);
        if ($pos === false) {
            return $url;
        }

        $relative = ltrim(substr($url, $pos + strlen($marker)), '/');

        return Storage::disk('s3')->url($relative);
    }

    public static function exists(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return true;
        }

        return Storage::disk(self::diskName())->exists(ltrim($path, '/'));
    }

    public static function deleteIfExists(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $marker = '/api/v1/storage/';
            $pos = strpos($path, $marker);
            if ($pos !== false) {
                $path = substr($path, $pos + strlen($marker));
            } else {
                return;
            }
        }

        $normalized = ltrim($path, '/');

        foreach (['public', 's3'] as $disk) {
            if (!config("filesystems.disks.{$disk}.driver")) {
                continue;
            }

            if (Storage::disk($disk)->exists($normalized)) {
                Storage::disk($disk)->delete($normalized);
            }
        }
    }
}
