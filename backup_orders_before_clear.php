<?php

/**
 * Backup Orders Data Before Clearing
 * 
 * Creates a backup of all order-related data before deletion
 * 
 * Usage: php backup_orders_before_clear.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  BACKUP ORDERS DATA\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

try {
    // Create backups directory if not exists
    $backupDir = __DIR__ . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "/orders_backup_{$timestamp}.json";
    
    echo "Creating backup...\n\n";
    
    // Get all orders with related data
    $orders = DB::table('orders')->get()->toArray();
    $orderItems = DB::table('order_items')->get()->toArray();
    $chatConversations = DB::table('chat_conversations')
        ->whereNotNull('order_id')
        ->get()
        ->toArray();
    $chatMessages = DB::table('chat_messages')
        ->whereIn('conversation_id', function($query) {
            $query->select('id')
                ->from('chat_conversations')
                ->whereNotNull('order_id');
        })
        ->get()
        ->toArray();
    
    $backup = [
        'backup_date' => now()->toIso8601String(),
        'database' => config('database.connections.mysql.database'),
        'counts' => [
            'orders' => count($orders),
            'order_items' => count($orderItems),
            'chat_conversations' => count($chatConversations),
            'chat_messages' => count($chatMessages),
        ],
        'data' => [
            'orders' => $orders,
            'order_items' => $orderItems,
            'chat_conversations' => $chatConversations,
            'chat_messages' => $chatMessages,
        ],
    ];
    
    // Save to JSON file
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($backupFile, $json);
    
    $fileSize = filesize($backupFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
    
    echo "✓ Backup created successfully!\n\n";
    echo "Details:\n";
    echo "  - File: {$backupFile}\n";
    echo "  - Size: {$fileSizeMB} MB\n";
    echo "  - Orders: " . count($orders) . "\n";
    echo "  - Order Items: " . count($orderItems) . "\n";
    echo "  - Chat Conversations: " . count($chatConversations) . "\n";
    echo "  - Chat Messages: " . count($chatMessages) . "\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "\n";
    echo "✗ ERROR: Failed to create backup\n\n";
    echo "Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}\n";
    echo "Line: {$e->getLine()}\n\n";
    exit(1);
}
