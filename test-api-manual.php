<?php

/**
 * Manual API Testing Script
 * Run with: php test-api-manual.php
 */

$baseUrl = 'http://127.0.0.1:8000/api/v1';
$results = [];

function makeRequest($method, $url, $data = null, $headers = []) {
    $ch = curl_init();
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    return [
        'status' => $statusCode,
        'headers' => $headers,
        'body' => json_decode($body, true),
        'raw_body' => $body,
    ];
}

function test($name, $callback) {
    global $results;
    echo "\n🧪 Testing: $name\n";
    echo str_repeat('-', 60) . "\n";
    
    try {
        $result = $callback();
        $results[] = ['test' => $name, 'status' => 'PASS', 'result' => $result];
        echo "✅ PASS\n";
        return $result;
    } catch (Exception $e) {
        $results[] = ['test' => $name, 'status' => 'FAIL', 'error' => $e->getMessage()];
        echo "❌ FAIL: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          API SECURITY TESTING SUITE                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

// Test 1: User Registration
$registerData = test('User Registration', function() use ($baseUrl) {
    global $baseUrl;
    $response = makeRequest('POST', "$baseUrl/auth/register", [
        'name' => 'Test User',
        'email' => 'test' . time() . '@example.com',
        'password' => 'SecureP@ss123',
        'password_confirmation' => 'SecureP@ss123',
        'role' => 'customer',
    ]);
    
    if ($response['status'] !== 201) {
        throw new Exception("Expected 201, got {$response['status']}");
    }
    
    if (!isset($response['body']['token'])) {
        throw new Exception("Token not returned");
    }
    
    echo "Token: " . substr($response['body']['token'], 0, 20) . "...\n";
    echo "User ID: " . $response['body']['user']['id'] . "\n";
    
    return $response['body'];
});

$token = $registerData['token'] ?? null;
$userId = $registerData['user']['id'] ?? null;

// Test 2: Registration with Weak Password
test('Registration with Weak Password (Should Fail)', function() use ($baseUrl) {
    $response = makeRequest('POST', "$baseUrl/auth/register", [
        'name' => 'Test User',
        'email' => 'weak' . time() . '@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
        'role' => 'customer',
    ]);
    
    if ($response['status'] !== 422) {
        throw new Exception("Expected 422, got {$response['status']}");
    }
    
    if (!isset($response['body']['errors']['password'])) {
        throw new Exception("Password validation error not returned");
    }
    
    echo "Validation errors: " . count($response['body']['errors']['password']) . "\n";
    return $response['body'];
});

// Test 3: Duplicate Email Registration
test('Duplicate Email Registration (Should Fail)', function() use ($baseUrl, $registerData) {
    if (!$registerData) return;
    
    $response = makeRequest('POST', "$baseUrl/auth/register", [
        'name' => 'Another User',
        'email' => $registerData['user']['email'],
        'password' => 'SecureP@ss123',
        'password_confirmation' => 'SecureP@ss123',
        'role' => 'customer',
    ]);
    
    if ($response['status'] !== 422) {
        throw new Exception("Expected 422, got {$response['status']}");
    }
    
    if (!isset($response['body']['errors']['email'])) {
        throw new Exception("Email validation error not returned");
    }
    
    echo "Email already taken error returned\n";
    return $response['body'];
});

// Test 4: Login with Valid Credentials
$loginData = test('Login with Valid Credentials', function() use ($baseUrl, $registerData) {
    if (!$registerData) throw new Exception("Registration failed, skipping");
    
    $response = makeRequest('POST', "$baseUrl/auth/login", [
        'email' => $registerData['user']['email'],
        'password' => 'SecureP@ss123',
    ]);
    
    if ($response['status'] !== 200) {
        throw new Exception("Expected 200, got {$response['status']}");
    }
    
    if (!isset($response['body']['token'])) {
        throw new Exception("Token not returned");
    }
    
    echo "Login successful\n";
    echo "Token: " . substr($response['body']['token'], 0, 20) . "...\n";
    
    return $response['body'];
});

// Test 5: Login with Invalid Credentials
test('Login with Invalid Credentials (Should Fail)', function() use ($baseUrl, $registerData) {
    if (!$registerData) throw new Exception("Registration failed, skipping");
    
    $response = makeRequest('POST', "$baseUrl/auth/login", [
        'email' => $registerData['user']['email'],
        'password' => 'WrongPassword123',
    ]);
    
    if ($response['status'] !== 422) {
        throw new Exception("Expected 422, got {$response['status']}");
    }
    
    // Check for generic error message (no user enumeration)
    $message = $response['body']['errors']['email'][0] ?? '';
    if (strpos($message, 'incorrect') === false) {
        throw new Exception("Generic error message not returned");
    }
    
    echo "Generic error message returned (no user enumeration)\n";
    return $response['body'];
});

// Test 6: Access Profile with Token
test('Access Profile with Valid Token', function() use ($baseUrl, $token) {
    if (!$token) throw new Exception("No token available, skipping");
    
    $response = makeRequest('GET', "$baseUrl/auth/me", null, [
        "Authorization: Bearer $token"
    ]);
    
    if ($response['status'] !== 200) {
        throw new Exception("Expected 200, got {$response['status']}");
    }
    
    if (!isset($response['body']['user'])) {
        throw new Exception("User data not returned");
    }
    
    echo "User: " . $response['body']['user']['name'] . "\n";
    echo "Email: " . $response['body']['user']['email'] . "\n";
    echo "Role: " . $response['body']['user']['role'] . "\n";
    
    return $response['body'];
});

// Test 7: Access Profile without Token
test('Access Profile without Token (Should Fail)', function() use ($baseUrl) {
    $response = makeRequest('GET', "$baseUrl/auth/me");
    
    if ($response['status'] !== 401) {
        throw new Exception("Expected 401, got {$response['status']}");
    }
    
    if ($response['body']['message'] !== 'Unauthenticated') {
        throw new Exception("Expected 'Unauthenticated' message");
    }
    
    echo "401 Unauthenticated returned\n";
    return $response['body'];
});

// Test 8: Access Profile with Invalid Token
test('Access Profile with Invalid Token (Should Fail)', function() use ($baseUrl) {
    $response = makeRequest('GET', "$baseUrl/auth/me", null, [
        "Authorization: Bearer invalid_token_12345"
    ]);
    
    if ($response['status'] !== 401) {
        throw new Exception("Expected 401, got {$response['status']}");
    }
    
    echo "401 Unauthenticated returned for invalid token\n";
    return $response['body'];
});

// Test 9: Update Profile
test('Update Profile', function() use ($baseUrl, $token) {
    if (!$token) throw new Exception("No token available, skipping");
    
    $response = makeRequest('PUT', "$baseUrl/auth/me", [
        'name' => 'Updated Test User',
        'phone_number' => '+1234567890',
    ], [
        "Authorization: Bearer $token"
    ]);
    
    if ($response['status'] !== 200) {
        throw new Exception("Expected 200, got {$response['status']}");
    }
    
    if ($response['body']['user']['name'] !== 'Updated Test User') {
        throw new Exception("Name not updated");
    }
    
    echo "Profile updated successfully\n";
    echo "New name: " . $response['body']['user']['name'] . "\n";
    
    return $response['body'];
});

// Test 10: Security Headers Check
test('Security Headers Present', function() use ($baseUrl) {
    $response = makeRequest('GET', "$baseUrl/auth/me");
    
    $requiredHeaders = [
        'X-Frame-Options',
        'X-Content-Type-Options',
        'X-Request-ID',
    ];
    
    $found = [];
    foreach ($requiredHeaders as $header) {
        if (stripos($response['headers'], $header) !== false) {
            $found[] = $header;
        }
    }
    
    if (count($found) < count($requiredHeaders)) {
        $missing = array_diff($requiredHeaders, $found);
        throw new Exception("Missing headers: " . implode(', ', $missing));
    }
    
    echo "All required security headers present\n";
    echo "Found: " . implode(', ', $found) . "\n";
    
    return $found;
});

// Test 11: Rate Limiting (Auth Endpoints)
test('Rate Limiting on Auth Endpoints', function() use ($baseUrl) {
    echo "Making 6 rapid login attempts...\n";
    
    $attempts = 0;
    $rateLimited = false;
    
    for ($i = 0; $i < 6; $i++) {
        $response = makeRequest('POST', "$baseUrl/auth/login", [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);
        
        $attempts++;
        
        if ($response['status'] === 429) {
            $rateLimited = true;
            echo "Rate limited after $attempts attempts\n";
            break;
        }
        
        usleep(100000); // 100ms delay
    }
    
    if (!$rateLimited) {
        throw new Exception("Rate limiting not triggered after $attempts attempts");
    }
    
    return ['attempts' => $attempts, 'rate_limited' => true];
});

// Test 12: Logout
test('Logout', function() use ($baseUrl, $token) {
    if (!$token) throw new Exception("No token available, skipping");
    
    $response = makeRequest('POST', "$baseUrl/auth/logout", null, [
        "Authorization: Bearer $token"
    ]);
    
    if ($response['status'] !== 200) {
        throw new Exception("Expected 200, got {$response['status']}");
    }
    
    echo "Logout successful\n";
    
    // Try to use token after logout
    $response2 = makeRequest('GET', "$baseUrl/auth/me", null, [
        "Authorization: Bearer $token"
    ]);
    
    if ($response2['status'] !== 401) {
        throw new Exception("Token still valid after logout");
    }
    
    echo "Token invalidated after logout\n";
    
    return $response['body'];
});

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST SUMMARY                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

$passed = 0;
$failed = 0;

foreach ($results as $result) {
    if ($result['status'] === 'PASS') {
        $passed++;
        echo "✅ {$result['test']}\n";
    } else {
        $failed++;
        echo "❌ {$result['test']}: {$result['error']}\n";
    }
}

echo "\n";
echo "Total Tests: " . count($results) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 All tests passed!\n";
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
}
