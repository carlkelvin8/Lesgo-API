<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

$client = new Client([
    'base_uri' => 'https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1/',
    'verify' => false,
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

echo "1. Logging in...\n";
try {
    $res = $client->post('auth/login', [
        'json' => [
            'email' => 'carl@gmail.com',
            'password' => 'password123',
            'device_name' => 'test'
        ]
    ]);
    
    $data = json_decode($res->getBody()->getContents(), true);
    $token = $data['token'];
    $partnerId = $data['user']['partner_id'] ?? null;
    
    if (!$partnerId) {
        // Find partner ID from /auth/me or it's returned in login
        echo "Partner ID not in login response. Trying to fetch profile...\n";
        $resMe = $client->get('auth/me', [
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);
        $meData = json_decode($resMe->getBody()->getContents(), true);
        $partnerId = $meData['user']['partner_id'] ?? null;
    }
    
    if (!$partnerId) {
        die("Could not find partner ID for this user.\n");
    }
    
    echo "Logged in. Token: " . substr($token, 0, 10) . "...\n";
    echo "Partner ID: $partnerId\n";
    
    echo "2. Updating partner...\n";
    $resPatch = $client->patch("partners/$partnerId", [
        'headers' => ['Authorization' => 'Bearer ' . $token],
        'json' => [
            'name' => 'Test Restaurant (Updated)'
        ]
    ]);
    
    echo "Success! Response: \n";
    echo $resPatch->getBody()->getContents() . "\n";
    
} catch (ClientException $e) {
    echo "Client Error (4xx):\n";
    echo $e->getResponse()->getBody()->getContents() . "\n";
} catch (ServerException $e) {
    echo "Server Error (5xx):\n";
    echo $e->getResponse()->getBody()->getContents() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
