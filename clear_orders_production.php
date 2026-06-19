<?php

/**
 * Clear Orders Data - Production Database
 * 
 * WARNING: This script deletes order data from production
 * Use only during testing phase
 * 
 * Usage: php clear_orders_production.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  CLEAR ORDERS DATA - PRODUCTION DATABASE\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Check current environment
$env = config('app.env');
$dbName = config('database.connections.mysql.database');

echo "Environment: {$env}\n";
echo "Database: {$dbName}\n";
echo "\n";

try {
    // Get counts before deletion
    $ordersCount = DB::table('orders')->count();
    $orderItemsCount = DB::table('order_items')->count();
    $chatMessagesCount = DB::table('chat_messages')->count();
    $chatConversationsCount = DB::table('chat_conversations')->count();
    
    echo "Current Data:\n";
    echo "  - Orders: {$ordersCount}\n";
    echo "  - Order Items: {$orderItemsCount}\n";
    echo "  - Chat Messages: {$chatMessagesCount}\n";
    echo "  - Chat Conversations: {$chatConversationsCount}\n";
    echo "\n";
    
    if ($ordersCount === 0) {
        echo "✓ No orders to delete. Database is already clean.\n\n";
        exit(0);
    }
    
    echo "⚠️  WARNING: This will DELETE all order data!\n";
    echo "⚠️  This action CANNOT be undone!\n";
    echo "\n";
    echo "Press ENTER to continue or Ctrl+C to cancel...\n";
    
    // Wait for user confirmation
    $handle = fopen("php://stdin", "r");
    fgets($handle);
    fclose($handle);
    
    echo "\n";
    echo "Starting deletion process...\n";
    echo "\n";
    
    DB::beginTransaction();
    
    try {
        // Step 1: Delete chat messages related to orders
        echo "[1/5] Deleting chat messages...\n";
        $deletedMessages = DB::table('chat_messages')
            ->whereIn('conversation_id', function($query) {
                $query->select('id')
                    ->from('chat_conversations')
                    ->whereNotNull('order_id');
            })
            ->delete();
        echo "      ✓ Deleted {$deletedMessages} chat messages\n\n";
        
        // Step 2: Delete chat conversations related to orders
        echo "[2/5] Deleting chat conversations...\n";
        $deletedConversations = DB::table('chat_conversations')
            ->whereNotNull('order_id')
            ->delete();
        echo "      ✓ Deleted {$deletedConversations} conversations\n\n";
        
        // Step 3: Delete order items
        echo "[3/5] Deleting order items...\n";
        $deletedItems = DB::table('order_items')->delete();
        echo "      ✓ Deleted {$deletedItems} order items\n\n";
        
        // Step 4: Delete order_menu_item_options (if exists)
        echo "[4/5] Deleting order menu item options...\n";
        try {
            $deletedOptions = DB::table('order_menu_item_options')->delete();
            echo "      ✓ Deleted {$deletedOptions} menu item options\n\n";
        } catch (\Exception $e) {
            echo "      ℹ Table 'order_menu_item_options' not found, skipping\n\n";
        }
        
        // Step 5: Delete orders
        echo "[5/5] Deleting orders...\n";
        $deletedOrders = DB::table('orders')->delete();
        echo "      ✓ Deleted {$deletedOrders} orders\n\n";
        
        // Commit transaction
        DB::commit();
        
        echo "════════════════════════════════════════════════════════════════\n";
        echo "  ✓ SUCCESS: All order data deleted!\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "\n";
        echo "Summary:\n";
        echo "  - Orders deleted: {$deletedOrders}\n";
        echo "  - Order items deleted: {$deletedItems}\n";
        echo "  - Chat messages deleted: {$deletedMessages}\n";
        echo "  - Chat conversations deleted: {$deletedConversations}\n";
        echo "\n";
        
        // Reset auto-increment IDs
        echo "Resetting auto-increment counters...\n";
        DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE order_items AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE chat_messages AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE chat_conversations AUTO_INCREMENT = 1');
        echo "✓ Auto-increment counters reset\n\n";
        
        echo "Database is now clean and ready for fresh testing!\n\n";
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    
} catch (\Exception $e) {
    echo "\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "  ✗ ERROR: Failed to clear orders\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}\n";
    echo "Line: {$e->getLine()}\n";
    echo "\n";
    echo "Transaction rolled back. No data was deleted.\n\n";
    exit(1);
}
