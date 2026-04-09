<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            
            // Event details
            $table->enum('event_type', ['enter', 'exit', 'dwell']);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address')->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('bearing_degrees', 5, 2)->nullable();
            
            // Timing
            $table->timestamp('event_time');
            $table->timestamp('dwell_start_time')->nullable(); // For dwell events
            $table->integer('dwell_duration_seconds')->nullable(); // For dwell events
            
            // Device and context
            $table->string('device_id')->nullable();
            $table->string('device_type')->nullable(); // 'ios', 'android', 'web'
            $table->json('device_info')->nullable();
            
            // Processing status
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->json('notification_results')->nullable(); // Results of notification attempts
            $table->boolean('webhook_sent')->default(false);
            $table->timestamp('webhook_sent_at')->nullable();
            
            // Additional data
            $table->json('metadata')->nullable();
            $table->string('session_id')->nullable(); // For grouping related events
            
            $table->timestamps();
            
            // Indexes
            $table->index(['geofence_id', 'event_time']);
            $table->index(['user_id', 'event_time']);
            $table->index(['order_id', 'event_time']);
            $table->index(['event_type', 'event_time']);
            $table->index(['notification_sent', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_events');
    }
};