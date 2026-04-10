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
            $table->string('pickup_address')->nullable()->after('dropoff_address_id');
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            $table->string('pickup_contact_name')->nullable()->after('pickup_lng');
            $table->string('pickup_contact_phone')->nullable()->after('pickup_contact_name');

            // Inline dropoff address
            $table->string('dropoff_address')->nullable()->after('pickup_contact_phone');
            $table->decimal('dropoff_lat', 10, 7)->nullable()->after('dropoff_address');
            $table->decimal('dropoff_lng', 10, 7)->nullable()->after('dropoff_lat');
            $table->string('dropoff_contact_name')->nullable()->after('dropoff_lng');
            $table->string('dropoff_contact_phone')->nullable()->after('dropoff_contact_name');

            // Order-level notes
            $table->text('notes')->nullable()->after('cancel_reason');
        });

        // Enhance lesbuy_items with more fields
        Schema::table('lesbuy_items', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('quantity');           // e.g. "pcs", "kg", "box"
            $table->text('notes')->nullable()->after('unit');                // special instructions per item
            $table->string('image_url')->nullable()->after('notes');         // item photo
            $table->decimal('actual_price', 10, 2)->nullable()->after('estimated_price'); // filled by driver
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
