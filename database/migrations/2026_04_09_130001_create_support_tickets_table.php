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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // AUTO-GENERATED: TKT-2026-001234
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            
            // Ticket details
            $table->string('subject');
            $table->text('description');
            $table->enum('category', [
                'order_issue',
                'payment_issue', 
                'driver_complaint',
                'app_bug',
                'feature_request',
                'account_issue',
                'refund_request',
                'general_inquiry',
                'other'
            ]);
            
            // Priority and status
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', [
                'open',
                'in_progress', 
                'waiting_customer',
                'waiting_internal',
                'resolved',
                'closed',
                'cancelled'
            ])->default('open');
            
            // Tracking
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            
            // Customer satisfaction
            $table->tinyInteger('satisfaction_rating')->nullable()->unsigned(); // 1-5
            $table->text('satisfaction_comment')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // Device info, app version, etc.
            $table->json('attachments')->nullable(); // File attachments
            
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['category', 'priority']);
            $table->index(['status', 'created_at']);
            $table->index('ticket_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};