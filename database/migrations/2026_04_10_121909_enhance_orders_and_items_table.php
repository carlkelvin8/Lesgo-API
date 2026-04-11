<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add inline address fields to orders so frontend can pass address
        // without needing a saved address ID
        Schema::table('orders', function (Blueprint $table) {
            // Inline pickup address (stored in meta already, but explicit columns for querying)
            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('pickup_contact_name')->nullable();
            $table->string('pickup_contact_phone')->nullable();

            // Inline dropoff address
            $table->string('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->string('dropoff_contact_name')->nullable();
            $table->string('dropoff_contact_phone')->nullable();

            // Order-level notes
            $table->text('notes')->nullable();
        });

        // Enhance lesbuy_items with more fields
        Schema::table('lesbuy_items', function (Blueprint $table) {
            $table->string('unit')->nullable();           // e.g. "pcs", "kg", "box"
            $table->text('notes')->nullable();                // special instructions per item
            $table->string('image_url')->nullable();         // item photo
            $table->decimal('actual_price', 10, 2)->nullable(); // filled by driver
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_address', 'pickup_lat', 'pickup_lng',
                'pickup_contact_name', 'pickup_contact_phone',
                'dropoff_address', 'dropoff_lat', 'dropoff_lng',
                'dropoff_contact_name', 'dropoff_contact_phone',
                'notes',
            ]);
        });

        Schema::table('lesbuy_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'notes', 'image_url', 'actual_price']);
        });
    }
};
