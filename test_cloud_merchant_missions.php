<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

$client = new Client([
    'base_uri' => 'https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1/',
    'verify' => false,
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ]
]);

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
    
    echo "Logged in. Fetching missions...\n";
    $resMissions = $client->get('merchant/missions', [
        'headers' => ['Authorization' => 'Bearer ' . $token]
    ]);
    
    echo $resMissions->getBody()->getContents() . "\n";
    
} catch (ClientException $e) {
    echo "Client Error:\n";
    echo $e->getResponse()->getBody()->getContents() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
