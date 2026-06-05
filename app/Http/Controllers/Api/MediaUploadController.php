<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaUploadController extends Controller
{
    /**
     * Upload a single media file and return a public URL for API consumers.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'context' => 'nullable|in:document,support,profile,general,attendance',
        ]);

        $user = $request->user();
        $context = $validated['context'] ?? 'general';
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $storedName = Str::uuid()->toString() . '.' . strtolower($extension);
        $directory = "uploads/{$context}/{$user->id}";
        $path = MediaStorageService::storeUploadedFile($file, $directory, $storedName);

        return $this->success([
            'url' => MediaStorageService::publicUrl($path),
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'context' => $context,
        ], 'File uploaded successfully');
    }
}
