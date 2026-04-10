<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            
            // Social platform details
            $table->string('platform'); // 'facebook', 'twitter', 'instagram', 'linkedin', 'whatsapp', 'telegram'
            $table->string('share_type'); // 'order_completed', 'service_review', 'milestone', 'achievement', 'referral'
            $table->string('share_url')->nullable(); // Generated share URL
            $table->string('external_post_id')->nullable(); // Platform's post ID if available
            
            // Share content
            $table->text('share_title');
            $table->text('share_description');
            $table->string('share_image_url')->nullable();
            $table->json('share_metadata')->nullable(); // Additional platform-specific data
            
            // Engagement tracking
            $table->integer('clicks')->default(0);
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('comments')->default(0);
            
            // Privacy and settings
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            // Analytics
            $table->json('analytics_data')->nullable(); // Platform analytics if available
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'platform']);
            $table->index(['order_id', 'share_type']);
            $table->index(['platform', 'shared_at']);
            $table->index(['is_public', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_shares');
    }
};