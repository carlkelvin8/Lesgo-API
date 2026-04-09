<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Geofence details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // 'delivery_zone', 'service_area', 'restricted_area', 'pickup_zone', 'partner_location', 'custom'
            $table->enum('shape', ['circle', 'polygon'])->default('circle');
            
            // Location data
            $table->decimal('center_latitude', 10, 8);
            $table->decimal('center_longitude', 11, 8);
            $table->integer('radius_meters')->nullable(); // For circular geofences
            $table->json('polygon_coordinates')->nullable(); // For polygon geofences
            
            // Trigger settings
            $table->boolean('trigger_on_enter')->default(true);
            $table->boolean('trigger_on_exit')->default(true);
            $table->boolean('trigger_on_dwell')->default(false);
            $table->integer('dwell_time_seconds')->nullable(); // Minimum time to trigger dwell
            
            // Notification settings
            $table->json('notification_types')->nullable(); // ['push', 'sms', 'email', 'webhook']
            $table->json('notification_recipients')->nullable(); // User IDs or roles to notify
            $table->text('enter_message')->nullable();
            $table->text('exit_message')->nullable();
            $table->text('dwell_message')->nullable();
            
            // Scheduling
            $table->json('active_days')->nullable(); // ['monday', 'tuesday', ...] or null for all days
            $table->time('active_start_time')->nullable();
            $table->time('active_end_time')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            
            // Status and metadata
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // 1=low, 2=medium, 3=high, 4=critical
            $table->json('metadata')->nullable(); // Additional configuration
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            
            $table->timestamps();
            
            // Indexes for spatial queries
            $table->index(['center_latitude', 'center_longitude']);
            $table->index(['type', 'is_active']);
            $table->index(['created_by', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};