<?php

// Simple health check script for Railway debugging
// This bypasses Laravel's full bootstrap to test basic PHP functionality

echo "=== LeSGo API Health Check ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";

// Check if critical files exist
$criticalFiles = [
    'artisan',
    'composer.json',
    'bootstrap/app.php',
    'routes/api.php',
    'app/Http/Controllers/Controller.php'
];

echo "\n=== File Check ===\n";
foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file MISSING\n";
    }
}

// Check environment variables
echo "\n=== Environment Variables ===\n";
$envVars = [
    'APP_KEY',
    'APP_ENV',
    'DB_HOST',
    'DB_DATABASE',
    'REDIS_HOST'
];

foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value) {
        if ($var === 'APP_KEY') {
            echo "✓ $var: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "✓ $var: $value\n";
        }
    } else {
        echo "✗ $var: NOT SET\n";
    }
}

// Test database connection (basic PDO)
echo "\n=== Database Connection Test ===\n";
try {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $dbname = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
    
    if ($host && $dbname && $username) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✓ Database connection successful\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();
        echo "✓ PostgreSQL Version: $version\n";
    } else {
        echo "✗ Database credentials not available\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

// Test Redis connection
echo "\n=== Redis Connection Test ===\n";
try {
    $redisHost = getenv('REDIS_HOST');
    $redisPort = getenv('REDIS_PORT') ?: '6379';
    $redisPassword = getenv('REDIS_PASSWORD');
    
    if ($redisHost) {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort, 5); // 5 second timeout
        
        if ($redisPassword) {
            $redis->auth($redisPassword);
        }
        
        $redis->ping();
        echo "✓ Redis connection successful\n";
    } else {
        echo "✗ Redis host not available\n";
    }
} catch (Exception $e) {
    echo "✗ Redis connection failed: " . $e->getMessage() . "\n";
}

// Try to bootstrap Laravel
echo "\n=== Laravel Bootstrap Test ===\n";
try {
    require_once 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    echo "✓ Laravel bootstrap successful\n";
    
    // Test if we can get the version
    $version = $app->version();
    echo "✓ Laravel Version: $version\n";
    
} catch (Exception $e) {
    echo "✗ Laravel bootstrap failed: " . $e->getMessage() . "\n";
}

echo "\n=== Health Check Complete ===\n";