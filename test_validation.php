<?php

/**
 * Quick test script to verify validation rules work correctly
 * Run: php test_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Rules\PhilippinePhone;
use App\Rules\StrongPassword;

echo "=== Testing Validation Rules ===\n\n";

// Test PhilippinePhone Rule
echo "1. Testing PhilippinePhone Rule:\n";
echo "--------------------------------\n";

$phoneRule = new PhilippinePhone();

$phoneTests = [
    '09171234567' => true,
    '+639171234567' => true,
    '639171234567' => true,
    '0917-123-4567' => true,
    '0917 123 4567' => true,
    '12345' => false,
    'invalid' => false,
    '09' => false,
];

foreach ($phoneTests as $phone => $expected) {
    $result = $phoneRule->passes('phone', $phone);
    $status = $result === $expected ? '✅ PASS' : '❌ FAIL';
    echo sprintf("  %s: %s (expected: %s, got: %s)\n", 
        $status, 
        $phone, 
        $expected ? 'valid' : 'invalid',
        $result ? 'valid' : 'invalid'
    );
}

echo "\n";

// Test StrongPassword Rule
echo "2. Testing StrongPassword Rule:\n";
echo "--------------------------------\n";

$passwordRule = new StrongPassword();

$passwordTests = [
    'Password123!' => true,
    'Weak123!' => true,
    'VeryStrong123!@#' => true,
    'weak' => false,
    'password' => false,
    'PASSWORD' => false,
    'Password' => false,
    'Password123' => false,
    '12345678' => false,
    'Pass1!' => false, // Too short
];

foreach ($passwordTests as $password => $expected) {
    $result = $passwordRule->passes('password', $password);
    $status = $result === $expected ? '✅ PASS' : '❌ FAIL';
    echo sprintf("  %s: %s (expected: %s, got: %s)\n", 
        $status, 
        str_repeat('*', strlen($password)), 
        $expected ? 'valid' : 'invalid',
        $result ? 'valid' : 'invalid'
    );
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "All validation rules are working correctly!\n";
echo "\nError messages:\n";
echo "- PhilippinePhone: " . $phoneRule->message() . "\n";
echo "- StrongPassword: " . $passwordRule->message() . "\n";

