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
        Schema::create('ratings_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            
            // Rating categories (1-5 scale)
            $table->smallInteger('overall_rating');
            $table->smallInteger('service_rating')->nullable();
            $table->smallInteger('driver_rating')->nullable();
            $table->smallInteger('delivery_time_rating')->nullable();
            $table->smallInteger('communication_rating')->nullable();
            $table->smallInteger('professionalism_rating')->nullable();
            
            // Review content
            $table->text('review_title')->nullable();
            $table->text('review_comment')->nullable();
            $table->json('review_tags')->nullable(); // ['fast', 'professional', 'friendly']
            
            // Media attachments
            $table->json('review_images')->nullable(); // Array of image URLs
            
            // Review metadata
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_verified')->default(true); // Only verified orders can review
            $table->boolean('is_featured')->default(false); // Featured reviews
            $table->boolean('is_public')->default(true);
            
            // Moderation
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('approved');
            $table->text('moderation_notes')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Response from business
            $table->text('business_response')->nullable();
            $table->timestamp('business_responded_at')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'order_id']);
            $table->index(['driver_id', 'overall_rating']);
            $table->index(['service_id', 'overall_rating']);
            $table->index(['status', 'is_public']);
            $table->index('created_at');
            
            // Ensure one review per order per user
            $table->unique(['user_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings_reviews');
    }
};