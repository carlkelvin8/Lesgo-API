<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Document details
            $table->string('document_type'); // 'driver_license', 'vehicle_registration', 'business_permit', 'valid_id', etc.
            $table->string('document_number')->nullable();
            $table->json('document_urls'); // Array of document image URLs
            $table->text('description')->nullable();
            
            // Verification status
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'expired'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            
            // Verification details
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For documents with expiration
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional document info
            $table->integer('verification_attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'document_type']);
            $table->index(['status', 'submitted_at']);
            $table->index(['verified_by', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};