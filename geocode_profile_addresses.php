<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$apiKey = 'AIzaSyBekzwaPQ9H0Zu3y7XOyUkrM-ny4XdVZXA';

// Get user
$user = App\Models\User::find(923);

if (!$user) {
    echo "User not found\n";
    exit(1);
}

$address1 = $user->address_line1;
$address2 = $user->address_line2;

if (empty($address1) && empty($address2)) {
    echo "No addresses to geocode\n";
    exit(0);
}

// Combine addresses
$fullAddress = trim("$address1 $address2");

echo "Geocoding address: $fullAddress\n";

// Call Google Geocoding API
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($fullAddress) . "&key=$apiKey";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['status'] === 'OK' && !empty($data['results'])) {
    $location = $data['results'][0]['geometry']['location'];
    $lat = $location['lat'];
    $lng = $location['lng'];
    
    echo "Found coordinates: $lat, $lng\n";
    echo "Formatted address: " . $data['results'][0]['formatted_address'] . "\n";
    
    // Create a new saved address with these coordinates
    $address = App\Models\Address::create([
        'user_id' => $user->id,
        'label' => 'Home',
        'contact_name' => $user->name,
        'contact_phone' => $user->phone_number ?? '',
        'address_line1' => $address1,
        'address_line2' => $address2,
        'latitude' => $lat,
        'longitude' => $lng,
        'is_default' => true,
    ]);
    
    echo "✅ Created saved address with ID: " . $address->id . "\n";
    echo "This address can now be used for orders and will show on the map!\n";
} else {
    echo "❌ Geocoding failed: " . $data['status'] . "\n";
    if (isset($data['error_message'])) {
        echo "Error: " . $data['error_message'] . "\n";
    }
}
