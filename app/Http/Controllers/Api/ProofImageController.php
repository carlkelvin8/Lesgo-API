<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProofImageController extends Controller
{
    /**
     * Serve proof image with proper CORS headers
     */
    public function show(Request $request, $orderId, $filename)
    {
        try {
            $path = "proof_images/{$orderId}/{$filename}";
            
            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found',
                    'path' => $path,
                ], 404);
            }
            
            $fullPath = Storage::disk('public')->path($path);
            
            // Get mime type
            $mimeType = mime_content_type($fullPath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }
            
            // Return file with CORS headers
            return response()->file($fullPath, [
                'Content-Type' => $mimeType,
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error serving image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
