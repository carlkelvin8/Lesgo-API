<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-Factor Authentication
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('method'); // totp, sms, email, backup_codes
            $table->string('secret')->nullable(); // TOTP secret
            $table->text('backup_codes')->nullable(); // JSON array of backup codes
            $table->string('phone_number')->nullable(); // For SMS 2FA
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('recovery_codes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'method']);
            $table->index(['user_id', 'is_enabled']);
        });

        // Biometric Authentication
        Schema::create('biometric_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('biometric_type'); // fingerprint, face_id, voice, iris
            $table->string('device_id'); // Unique device identifier
            $table->string('biometric_hash'); // Hashed biometric template
            $table->string('public_key')->nullable(); // For cryptographic verification
            $table->boolean('is_active')->default(true);
            $table->timestamp('enrolled_at');
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            $table->json('device_info')->nullable(); // Device details
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'biometric_type']);
            $table->index(['user_id', 'is_active']);
        });

        // GDPR Data Requests
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('request_type'); // access, portability, rectification, erasure, restriction
            $table->string('status'); // pending, processing, completed, rejected
            $table->text('description')->nullable();
            $table->json('requested_data')->nullable(); // Specific data types requested
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('processed_by')->nullable();
            $table->text('processing_notes')->nullable();
            $table->string('export_file_path')->nullable();
            $table->timestamp('export_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['request_type', 'status']);
        });

        // Data Retention Policies
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('data_type'); // user_data, audit_logs, analytics_events
            $table->string('category'); // personal, financial, operational, system
            $table->integer('retention_days');
            $table->string('deletion_method'); // soft_delete, hard_delete, anonymize
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('conditions')->nullable(); // Conditions for retention
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['data_type', 'category']);
            $table->index(['is_active']);
        });

        // Rate Limiting & IP Management
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint_pattern'); // API endpoint pattern
            $table->string('method')->nullable(); // HTTP method (null = all)
            $table->integer('max_attempts');
            $table->integer('window_minutes');
            $table->string('scope'); // ip, user, global
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Higher priority rules checked first
            $table->json('conditions')->nullable(); // Additional conditions
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('ip_whitelist', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('ip_range')->nullable(); // CIDR notation
            $table->string('type'); // permanent, temporary, api_access
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->string('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ip_address', 'is_active']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('ip_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('ip_range')->nullable(); // CIDR notation
            $table->string('reason'); // suspicious_activity, abuse, security_threat
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->string('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ip_address', 'is_active']);
            $table->index(['reason', 'is_active']);
        });

        // PCI DSS Compliance
        Schema::create('payment_security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('transaction_id')->nullable();
            $table->string('event_type'); // card_data_access, payment_processing, token_creation
            $table->string('pci_event_category'); // data_access, authentication, authorization
            $table->string('masked_card_number')->nullable(); // Last 4 digits only
            $table->string('payment_method');
            $table->string('processor'); // xendit, gcash, maya
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->boolean('is_compliant')->default(true);
            $table->text('compliance_notes')->nullable();
            $table->json('security_context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['event_type', 'processed_at']);
            $table->index(['is_compliant', 'processed_at']);
        });

        // Security Configuration
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->string('setting_value');
            $table->string('data_type'); // string, integer, boolean, json
            $table->text('description')->nullable();
            $table->string('category'); // authentication, authorization, encryption, compliance
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('requires_restart')->default(false);
            $table->string('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
        Schema::dropIfExists('payment_security_logs');
        Schema::dropIfExists('ip_blacklist');
        Schema::dropIfExists('ip_whitelist');
        Schema::dropIfExists('rate_limit_rules');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('gdpr_requests');
        Schema::dropIfExists('biometric_auth');
        Schema::dropIfExists('two_factor_auth');
    }
};