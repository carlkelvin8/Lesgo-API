<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate audit_logs table with correct columns
        Schema::dropIfExists('audit_logs');
        
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type')->nullable();
            $table->string('event_category')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->bigInteger('resource_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('request_id')->nullable();
            $table->string('risk_level')->default('medium');
            $table->boolean('is_suspicious')->default(false);
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useNow();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['event_category', 'created_at']);
            $table->index('risk_level');
            $table->index('is_suspicious');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
