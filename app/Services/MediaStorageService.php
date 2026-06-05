<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Central media storage — all uploads go to Laravel Cloud object storage (S3/R2).
 */
class MediaStorageService
{
    public static function diskName(): string
    {
        $disk = (string) config('filesystems.media_disk', 's3');

        if ($disk !== 's3') {
            throw new RuntimeException(
                'Media uploads must use S3. Set MEDIA_DISK=s3 in your environment.'
            );
        }

        return 's3';
    }

    public static function usesCloudStorage(): bool
    {
        return self::isS3Configured();
    }

    public static function isS3Configured(): bool
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $key = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');

        return !empty($bucket) && !empty($key) && !empty($secret);
    }

    public static function activeDiskName(): string
    {
        self::assertS3Ready();

        return 's3';
    }

    public static function assertS3Ready(): void
    {
        self::diskName();

        if (!self::isS3Configured()) {
            throw new RuntimeException(
                'S3 object storage is not configured. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET.'
            );
        }
    }

    public static function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $storedName = null
    ): string {
        self::assertS3Ready();

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = $storedName ?? (Str::uuid()->toString() . '.' . strtolower($extension));

        $path = $file->storeAs($directory, $filename, [
            'disk' => 's3',
            'visibility' => 'public',
        ]);

        if (!$path) {
            throw new RuntimeException('Failed to store uploaded file on S3.');
        }

        return self::normalizeStoredPath($path) ?? $path;
    }

    public static function putContents(string $path, string $contents): string
    {
        self::assertS3Ready();

        $normalized = self::normalizeStoredPath($path) ?? ltrim($path, '/');
        Storage::disk('s3')->put($normalized, $contents, 'public');

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

        if (self::isS3Configured()) {
            try {
                return Storage::disk('s3')->url($relative);
            } catch (\Throwable $e) {
                \Log::error('S3 URL generation failed', [
                    'path'  => $relative,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $cloudBase = rtrim((string) config('filesystems.disks.s3.url'), '/');
        if ($cloudBase !== '') {
            return $cloudBase . '/' . ltrim($relative, '/');
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

        if (!self::isS3Configured()) {
            return false;
        }

        try {
            return Storage::disk('s3')->exists($relative);
        } catch (\Throwable $e) {
            \Log::warning('S3 exists check failed', [
                'path'  => $relative,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

        if (!self::isS3Configured()) {
            return;
        }

        try {
            if (Storage::disk('s3')->exists($relative)) {
                Storage::disk('s3')->delete($relative);
            }
        } catch (\Throwable $e) {
            \Log::warning('S3 delete failed', [
                'path'  => $relative,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
