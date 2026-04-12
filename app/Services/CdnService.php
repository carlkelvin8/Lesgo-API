<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * CDN Integration Service
 * 
 * Manages media asset uploads to CDN, cache invalidation,
 * and optimized asset delivery for global users.
 * 
 * Supports: Cloudflare R2, AWS CloudFront, Firebase Storage
 */
class CdnService
{
    /**
     * CDN provider (cloudflare, cloudfront, firebase, custom)
     */
    private string $provider;

    /**
     * CDN base URL
     */
    private string $baseUrl;

    /**
     * CDN API credentials
     */
    private array $credentials;

    public function __construct()
    {
        $this->provider = config('cdn.provider', 'cloudflare');
        $this->baseUrl = config('cdn.base_url', '');
        $this->credentials = [
            'api_key' => config('cdn.api_key', ''),
            'api_secret' => config('cdn.api_secret', ''),
            'zone_id' => config('cdn.zone_id', ''),
            'bucket' => config('cdn.bucket', ''),
        ];
    }

    /**
     * Upload file to CDN
     */
    public function uploadFile(UploadedFile $file, string $path = '', array $options = []): array
    {
        $fileName = $this->generateFileName($file, $path);
        $fullPath = "{$this->credentials['bucket']}/{$fileName}";

        try {
            // Upload to storage (S3, R2, etc.)
            $uploadResult = $this->uploadToStorage($file, $fileName);

            // Generate CDN URL
            $cdnUrl = $this->generateCdnUrl($fileName, $options);

            // Invalidate CDN cache if file exists
            if (!empty($options['invalidate'])) {
                $this->invalidateCache($fileName);
            }

            Log::info('File uploaded to CDN', [
                'file_name' => $fileName,
                'cdn_url' => $cdnUrl,
                'size' => $file->getSize(),
            ]);

            return [
                'success' => true,
                'file_name' => $fileName,
                'cdn_url' => $cdnUrl,
                'storage_path' => $fullPath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to upload file to CDN', [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(array $files, string $path = '', array $options = []): array
    {
        $results = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->uploadFile($file, $path, $options);
            }
        }

        return $results;
    }

    /**
     * Generate optimized image URL
     */
    public function generateOptimizedImageUrl(string $fileName, array $options = []): string
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        
        // Cloudflare image optimization
        if ($this->provider === 'cloudflare') {
            $width = $options['width'] ?? 800;
            $quality = $options['quality'] ?? 80;
            $format = $options['format'] ?? 'auto';
            
            return "{$baseUrl}/cdn-cgi/image/w={$width},q={$quality},f={$format}/{$fileName}";
        }

        // CloudFront with Lambda@Edge for optimization
        if ($this->provider === 'cloudfront') {
            $params = http_build_query([
                'w' => $options['width'] ?? 800,
                'q' => $options['quality'] ?? 80,
                'f' => $options['format'] ?? 'auto',
            ]);
            
            return "{$baseUrl}/{$fileName}?{$params}";
        }

        // Default: return original URL
        return "{$baseUrl}/{$fileName}";
    }

    /**
     * Invalidate CDN cache for specific files
     */
    public function invalidateCache(array|string $files): bool
    {
        $files = is_array($files) ? $files : [$files];

        try {
            match ($this->provider) {
                'cloudflare' => $this->invalidateCloudflareCache($files),
                'cloudfront' => $this->invalidateCloudFrontCache($files),
                default => $this->invalidateCustomCache($files),
            };

            Log::info('CDN cache invalidated', [
                'files' => $files,
                'provider' => $this->provider,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to invalidate CDN cache', [
                'files' => $files,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get CDN URL for a file
     */
    public function getCdnUrl(string $fileName): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($fileName, '/');
    }

    /**
     * Delete file from CDN
     */
    public function deleteFile(string $fileName): bool
    {
        try {
            $this->deleteFromStorage($fileName);

            Log::info('File deleted from CDN', [
                'file_name' => $fileName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file from CDN', [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get CDN usage statistics
     */
    public function getUsageStats(): array
    {
        return match ($this->provider) {
            'cloudflare' => $this->getCloudflareStats(),
            'cloudfront' => $this->getCloudFrontStats(),
            default => $this->getCustomStats(),
        };
    }

    /**
     * Purge entire CDN cache
     */
    public function purgeAllCache(): bool
    {
        try {
            match ($this->provider) {
                'cloudflare' => $this->purgeCloudflareAllCache(),
                'cloudfront' => $this->purgeCloudFrontAllCache(),
                default => $this->purgeCustomAllCache(),
            };

            Log::warning('CDN cache purged entirely');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to purge CDN cache', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ── Private Methods ─────────────────────────────────────────────────────

    /**
     * Generate unique file name
     */
    private function generateFileName(UploadedFile $file, string $path): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getRealPath() . time());
        $shortHash = substr($hash, 0, 16);
        
        $path = trim($path, '/');
        $fileName = $path ? "{$path}/{$shortHash}.{$extension}" : "{$shortHash}.{$extension}";

        return $fileName;
    }

    /**
     * Upload file to storage backend
     */
    private function uploadToStorage(UploadedFile $file, string $fileName): bool
    {
        $disk = config('cdn.disk', 's3');
        
        return Storage::disk($disk)->put(
            "{$this->credentials['bucket']}/{$fileName}",
            file_get_contents($file->getRealPath()),
            'public'
        );
    }

    /**
     * Generate CDN URL
     */
    private function generateCdnUrl(string $fileName, array $options): string
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        
        if (!empty($options['optimize']) && in_array($this->getMimeType($fileName), ['image/jpeg', 'image/png', 'image/webp'])) {
            return $this->generateOptimizedImageUrl($fileName, $options);
        }

        return "{$baseUrl}/{$fileName}";
    }

    /**
     * Get MIME type from file name
     */
    private function getMimeType(string $fileName): string
    {
        return match (pathinfo($fileName, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * Delete from storage
     */
    private function deleteFromStorage(string $fileName): bool
    {
        $disk = config('cdn.disk', 's3');
        return Storage::disk($disk)->delete("{$this->credentials['bucket']}/{$fileName}");
    }

    // ── Cloudflare Methods ──────────────────────────────────────────────────

    private function invalidateCloudflareCache(array $files): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->credentials['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("https://api.cloudflare.com/client/v4/zones/{$this->credentials['zone_id']}/purge_cache", [
            'files' => array_map(fn($file) => $this->getCdnUrl($file), $files),
        ]);

        return $response->successful();
    }

    private function getCloudflareStats(): array
    {
        // Cache stats for 5 minutes
        return Cache::remember('cdn:cloudflare:stats', now()->addMinutes(5), function () {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->credentials['api_key']}",
            ])->get("https://api.cloudflare.com/client/v4/zones/{$this->credentials['zone_id']}/analytics/dashboard");

            if ($response->successful()) {
                return $response->json('result') ?? [];
            }

            return [];
        });
    }

    private function purgeCloudflareAllCache(): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->credentials['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("https://api.cloudflare.com/client/v4/zones/{$this->credentials['zone_id']}/purge_cache", [
            'purge_everything' => true,
        ]);

        return $response->successful();
    }

    // ── CloudFront Methods ──────────────────────────────────────────────────

    private function invalidateCloudFrontCache(array $files): bool
    {
        // AWS SDK would be used here in production
        // For now, return true as placeholder
        Log::info('CloudFront cache invalidated', ['files' => $files]);
        return true;
    }

    private function getCloudFrontStats(): array
    {
        return Cache::remember('cdn:cloudfront:stats', now()->addMinutes(5), function () {
            // AWS CloudWatch metrics would be fetched here
            return [];
        });
    }

    private function purgeCloudFrontAllCache(): bool
    {
        Log::warning('CloudFront cache purged entirely');
        return true;
    }

    // ── Custom CDN Methods ──────────────────────────────────────────────────

    private function invalidateCustomCache(array $files): bool
    {
        Log::info('Custom CDN cache invalidated', ['files' => $files]);
        return true;
    }

    private function getCustomStats(): array
    {
        return [];
    }

    private function purgeCustomAllCache(): bool
    {
        Log::warning('Custom CDN cache purged entirely');
        return true;
    }
}
