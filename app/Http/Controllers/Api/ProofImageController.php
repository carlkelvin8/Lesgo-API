<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProofImageController extends Controller
{
    /**
     * Serve proof-of-delivery images with CORS headers.
     * Supports object storage (S3/R2) and legacy public disk files.
     */
    public function show(Request $request, $orderId, $filename)
    {
        try {
            $path = "proof_images/{$orderId}/{$filename}";

            if (MediaStorageService::isCloudDiskConfigured()
                && MediaStorageService::exists($path)) {
                $publicUrl = MediaStorageService::publicUrl($path);
                if ($publicUrl) {
                    return redirect($publicUrl);
                }

                return $this->streamFromCloudDisk($path);
            }

            if (Storage::disk('public')->exists($path)) {
                $fullPath = Storage::disk('public')->path($path);
                $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';

                return response()->file($fullPath, [
                    'Content-Type'                => $mimeType,
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                    'Access-Control-Allow-Headers' => '*',
                    'Cache-Control'               => 'public, max-age=31536000',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Image not found',
                'path'    => $path,
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error serving image: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function streamFromCloudDisk(string $path): StreamedResponse
    {
        $disk = MediaStorageService::resolveMediaDisk();
        $relative = MediaStorageService::normalizeStoredPath($path) ?? $path;
        $mimeType = Storage::disk($disk)->mimeType($relative) ?: 'image/jpeg';

        return response()->stream(function () use ($disk, $relative) {
            $stream = Storage::disk($disk)->readStream($relative);
            if ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type'                 => $mimeType,
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control'                => 'public, max-age=31536000',
        ]);
    }
}
