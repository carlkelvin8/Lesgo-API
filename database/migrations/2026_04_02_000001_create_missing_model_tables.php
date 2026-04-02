<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all tables that have models but no migration yet:
 *   - customer_profiles
 *   - partner_branches
 *   - vehicles
 *   - wallets
 *   - wallet_transactions
 *
 * Also adds missing columns to existing tables:
 *   - partners: tax_id, support_email, support_phone
 *   - driver_profiles: id_document_path
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── customer_profiles ─────────────────────────────────────────────────
        if (!Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->date('date_of_birth')->nullable();
                $table->string('gender')->nullable();
                $table->foreignId('default_address_id')->nullable()->constrained('addresses')->nullOnDelete();
                $table->timestamps();
            });
        }

        // ── partner_branches ──────────────────────────────────────────────────
        if (!Schema::hasTable('partner_branches')) {
            Schema::create('partner_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->string('name');
                $table->string('phone_number')->nullable();
                $table->string('address_line1')->nullable();
                $table->string('address_line2')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('country')->nullable();
                $table->string('postal_code')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->json('opening_hours')->nullable();
                $table->timestamps();

                $table->index('partner_id');
            });
        }

        // ── vehicles ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('driver_profiles')->cascadeOnDelete();
                $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
                $table->string('type')->nullable();
                $table->string('plate_number')->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->string('color')->nullable();
                $table->integer('year')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index(['driver_id', 'is_primary']);
            });
        }

        // ── wallets ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('balance', 12, 2)->default(0.00);
                $table->string('currency', 3)->default('PHP');
                $table->timestamps();
            });
        }

        // ── wallet_transactions ───────────────────────────────────────────────
        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
                $table->string('type');
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_before', 12, 2)->default(0);
                $table->decimal('balance_after', 12, 2)->default(0);
                $table->string('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['wallet_id', 'type']);
                $table->index(['source_type', 'source_id']);
            });
        }

        // ── partners: add missing columns ─────────────────────────────────────
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('business_type');
            }
            if (!Schema::hasColumn('partners', 'support_email')) {
                $table->string('support_email')->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('partners', 'support_phone')) {
                $table->string('support_phone')->nullable()->after('support_email');
            }
        });

        // ── driver_profiles: add missing column ───────────────────────────────
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_profiles', 'id_document_path')) {
                $table->string('id_document_path')->nullable()->after('license_expiry_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('driver_profiles', 'id_document_path')) {
                $table->dropColumn('id_document_path');
            }
        });

        Schema::table('partners', function (Blueprint $table) {
            $cols = array_filter(['tax_id', 'support_email', 'support_phone'], fn($c) => Schema::hasColumn('partners', $c));
            if ($cols) $table->dropColumn(array_values($cols));
        });

        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('partner_branches');
        Schema::dropIfExists('customer_profiles');
    }
};
