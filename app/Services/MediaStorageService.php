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
    /**
     * Find the first object-storage disk that Laravel Cloud (or env) has configured.
     */
    public static function findConfiguredMediaDisk(): ?string
    {
        $candidates = array_unique(array_filter([
            env('MEDIA_DISK'),
            (string) config('filesystems.default'),
            env('FILESYSTEM_DISK'),
            (string) config('filesystems.media_disk'),
        ], fn ($value) => $value !== null && $value !== ''));

        foreach ($candidates as $disk) {
            $disk = (string) $disk;
            if (in_array($disk, ['local', 'public'], true)) {
                continue;
            }
            if (self::isDiskConfigured($disk)) {
                return $disk;
            }
        }

        foreach (array_keys((array) config('filesystems.disks', [])) as $disk) {
            if (self::isDiskConfigured($disk)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Disk used for media uploads.
     */
    public static function resolveMediaDisk(): string
    {
        $configured = self::findConfiguredMediaDisk();
        if ($configured !== null) {
            return $configured;
        }

        return (string) (config('filesystems.media_disk')
            ?: config('filesystems.default')
            ?: 's3');
    }

    public static function isDiskConfigured(string $disk): bool
    {
        $config = (array) config("filesystems.disks.{$disk}", []);

        if (($config['driver'] ?? null) !== 's3') {
            return false;
        }

        $bucket = $config['bucket'] ?? null;
        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;

        return !empty($bucket) && !empty($key) && !empty($secret);
    }

    public static function diskConfig(?string $disk = null): array
    {
        $disk ??= self::resolveMediaDisk();

        return (array) config("filesystems.disks.{$disk}", []);
    }

    public static function usesCloudStorage(): bool
    {
        return self::findConfiguredMediaDisk() !== null;
    }

    public static function isCloudDiskConfigured(): bool
    {
        return self::findConfiguredMediaDisk() !== null;
    }

    /** @deprecated Use isCloudDiskConfigured() */
    public static function isS3Configured(): bool
    {
        return self::isCloudDiskConfigured();
    }

    public static function activeDiskName(): string
    {
        self::assertCloudDiskReady();

        return self::resolveMediaDisk();
    }

    public static function assertCloudDiskReady(): void
    {
        $configured = self::findConfiguredMediaDisk();

        if ($configured === null) {
            $knownDisks = implode(', ', array_keys((array) config('filesystems.disks', [])));
            $hasCloudConfig = isset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG']) ? 'yes' : 'no';
            $hasAwsKey = ! empty($_SERVER['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID')) ? 'yes' : 'no';

            throw new RuntimeException(
                'Object storage is not configured. In Laravel Cloud: attach a public bucket to this environment, '
                . 'redeploy, then retry. Known disks: '
                . $knownDisks
                . " (cloud_disk_config={$hasCloudConfig}, aws_key={$hasAwsKey})"
            );
        }
    }

    /** @deprecated Use assertCloudDiskReady() */
    public static function assertS3Ready(): void
    {
        self::assertCloudDiskReady();
    }

    public static function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $storedName = null
    ): string {
        self::assertCloudDiskReady();

        $disk = self::resolveMediaDisk();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = $storedName ?? (Str::uuid()->toString() . '.' . strtolower($extension));

        try {
            $path = $file->storeAs($directory, $filename, [
                'disk' => $disk,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Object storage upload failed', [
                'disk'  => $disk,
                'dir'   => $directory,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to upload image to object storage: ' . $e->getMessage(),
                previous: $e
            );
        }

        if (!$path) {
            throw new RuntimeException('Failed to store uploaded file on object storage.');
        }

        return self::normalizeStoredPath($path) ?? $path;
    }

    public static function putContents(string $path, string $contents): string
    {
        self::assertCloudDiskReady();

        $disk = self::resolveMediaDisk();
        $normalized = self::normalizeStoredPath($path) ?? ltrim($path, '/');

        try {
            Storage::disk($disk)->put($normalized, $contents);
        } catch (\Throwable $e) {
            \Log::error('Object storage put failed', [
                'disk'  => $disk,
                'path'  => $normalized,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to save file to object storage: ' . $e->getMessage(),
                previous: $e
            );
        }

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

            $cloudBase = self::cloudPublicBaseUrl();
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

        if (self::isCloudDiskConfigured()) {
            $disk = self::resolveMediaDisk();

            try {
                return Storage::disk($disk)->url($relative);
            } catch (\Throwable $e) {
                \Log::error('Object storage URL generation failed', [
                    'disk'  => $disk,
                    'path'  => $relative,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $cloudBase = self::cloudPublicBaseUrl();
        if ($cloudBase !== '') {
            return $cloudBase . '/' . ltrim($relative, '/');
        }

        return url('/api/v1/storage/' . $relative);
    }

    public static function cloudPublicBaseUrl(): string
    {
        if (self::isCloudDiskConfigured()) {
            $config = self::diskConfig(self::resolveMediaDisk());

            return rtrim((string) ($config['url'] ?? ''), '/');
        }

        return rtrim((string) config('filesystems.disks.s3.url', ''), '/');
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

        if (!self::isCloudDiskConfigured()) {
            return false;
        }

        $disk = self::resolveMediaDisk();

        try {
            return Storage::disk($disk)->exists($relative);
        } catch (\Throwable $e) {
            \Log::warning('Object storage exists check failed', [
                'disk'  => $disk,
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

        if (!self::isCloudDiskConfigured()) {
            return;
        }

        $disk = self::resolveMediaDisk();

        try {
            if (Storage::disk($disk)->exists($relative)) {
                Storage::disk($disk)->delete($relative);
            }
        } catch (\Throwable $e) {
            \Log::warning('Object storage delete failed', [
                'disk'  => $disk,
                'path'  => $relative,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
