<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = \Illuminate\Support\Facades\Schema::getColumnListing('orders');

if (in_array('platform_share', $columns)) {
    echo "✅ platform_share column EXISTS\n";
} else {
    echo "❌ platform_share column DOES NOT EXIST\n";
    echo "\nAdding column manually...\n";
    
    try {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE orders ADD COLUMN platform_share DECIMAL(10, 2) DEFAULT 0.00');
        echo "✅ Column added successfully!\n";
    } catch (\Exception $e) {
        echo "❌ Error adding column: " . $e->getMessage() . "\n";
    }
}

echo "\nAll columns in orders table:\n";
foreach ($columns as $column) {
    echo "  - $column\n";
}
