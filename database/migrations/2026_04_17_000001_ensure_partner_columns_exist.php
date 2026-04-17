<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures all required partner columns exist regardless of previous migration state.
 * This is a safety migration that uses hasColumn guards throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'logo_url')) {
                $table->string('logo_url')->nullable();
            }
            if (!Schema::hasColumn('partners', 'cover_image_url')) {
                $table->string('cover_image_url')->nullable();
            }
            if (!Schema::hasColumn('partners', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('partners', 'category')) {
                $table->string('category')->nullable();
            }
            if (!Schema::hasColumn('partners', 'tags')) {
                $table->json('tags')->nullable();
            }
            if (!Schema::hasColumn('partners', 'cuisine_types')) {
                $table->json('cuisine_types')->nullable();
            }
            if (!Schema::hasColumn('partners', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0.00);
            }
            if (!Schema::hasColumn('partners', 'total_reviews')) {
                $table->integer('total_reviews')->default(0);
            }
            if (!Schema::hasColumn('partners', 'delivery_fee')) {
                $table->decimal('delivery_fee', 8, 2)->default(0.00);
            }
            if (!Schema::hasColumn('partners', 'min_order_amount')) {
                $table->integer('min_order_amount')->default(0);
            }
            if (!Schema::hasColumn('partners', 'estimated_delivery_minutes')) {
                $table->integer('estimated_delivery_minutes')->default(30);
            }
            if (!Schema::hasColumn('partners', 'is_open')) {
                $table->boolean('is_open')->default(true);
            }
            if (!Schema::hasColumn('partners', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
            if (!Schema::hasColumn('partners', 'accepts_online_payment')) {
                $table->boolean('accepts_online_payment')->default(true);
            }
            if (!Schema::hasColumn('partners', 'opening_hours')) {
                $table->json('opening_hours')->nullable();
            }
            if (!Schema::hasColumn('partners', 'slug')) {
                $table->string('slug')->unique()->nullable();
            }
            if (!Schema::hasColumn('partners', 'tax_id')) {
                $table->string('tax_id')->nullable();
            }
            if (!Schema::hasColumn('partners', 'support_email')) {
                $table->string('support_email')->nullable();
            }
            if (!Schema::hasColumn('partners', 'support_phone')) {
                $table->string('support_phone')->nullable();
            }
        });

        Schema::table('partner_branches', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_branches', 'logo_url')) {
                $table->string('logo_url')->nullable();
            }
            if (!Schema::hasColumn('partner_branches', 'is_open')) {
                $table->boolean('is_open')->default(true);
            }
            if (!Schema::hasColumn('partner_branches', 'estimated_delivery_minutes')) {
                $table->integer('estimated_delivery_minutes')->default(30);
            }
            if (!Schema::hasColumn('partner_branches', 'delivery_fee')) {
                $table->decimal('delivery_fee', 8, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op — this is a safety migration
    }
};
