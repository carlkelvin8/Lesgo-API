<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;

// Test the addresses endpoint
$url = 'http://127.0.0.1:8000/api/v1/addresses';
$token = '1079|LSAhim8GJ10O2BK...'; // Use the token from the logs

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";