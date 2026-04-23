<?php
// Simple image server with CORS headers
// Usage: /serve-image.php?path=proof_images/412/filename.jpg

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the requested path
$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'No path specified']);
    exit;
}

// Security: prevent directory traversal
$path = str_replace(['../', '..\\'], '', $path);

// Build full path
$storagePath = __DIR__ . '/../storage/app/public/' . $path;

// Check if file exists
if (!file_exists($storagePath) || !is_file($storagePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $storagePath);
finfo_close($finfo);

// Set content type
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($storagePath));
header('Cache-Control: public, max-age=31536000');

// Output file
readfile($storagePath);
