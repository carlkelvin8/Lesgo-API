<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Who triggered the event
            
            // Event details
            $table->string('event_type'); // order_created, driver_assigned, picked_up, etc.
            $table->string('event_title');
            $table->text('event_description')->nullable();
            $table->enum('event_category', ['order', 'payment', 'delivery', 'system'])->default('order');
            
            // Location data
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('location_address')->nullable();
            
            // Additional data
            $table->json('metadata')->nullable(); // Additional event data
            $table->json('attachments')->nullable(); // Photos, documents
            
            // Visibility
            $table->boolean('is_visible_to_customer')->default(true);
            $table->boolean('is_milestone')->default(false); // Major milestones
            
            // Timing
            $table->timestamp('event_time')->default(now());
            $table->timestamps();

            // Indexes
            $table->index(['order_id', 'event_time']);
            $table->index(['event_type', 'event_time']);
            $table->index(['is_visible_to_customer', 'is_milestone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_tracking_events');
    }
};