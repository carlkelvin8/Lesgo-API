<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for all heavily-queried columns.
 * Covers: orders, payments, driver_profiles, services, addresses, lesbuy_items, users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');                          // role-based filtering
            $table->index('created_at');                    // date-range reports
        });

        // ── driver_profiles ──────────────────────────────────────────────────
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->index('status');                        // filter by active/offline
            $table->index(['partner_id', 'status']);        // partner scoped driver list
        });

        // ── services ─────────────────────────────────────────────────────────
        Schema::table('services', function (Blueprint $table) {
            $table->index(['is_active', 'partner_id']);     // public service listing
        });

        // ── addresses ────────────────────────────────────────────────────────
        Schema::table('addresses', function (Blueprint $table) {
            $table->index(['user_id', 'is_default']);       // default address lookup
        });

        // ── orders ───────────────────────────────────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'status']);       // customer order list
            $table->index(['driver_id', 'status']);         // driver active orders
            $table->index(['partner_id', 'status']);        // partner order list
            $table->index(['status', 'created_at']);        // stale order job + reports
            $table->index(['status', 'updated_at']);        // completed/cancelled reports
            $table->index('payment_status');                // payment status filter
            $table->index('created_at');                    // date-range queries
        });

        // ── payments ─────────────────────────────────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['customer_id', 'status']);       // customer payment list
            $table->index(['order_id', 'status']);          // duplicate payment check
            $table->index(['status', 'paid_at']);           // revenue reports
        });

        // ── lesbuy_items ─────────────────────────────────────────────────────
        Schema::table('lesbuy_items', function (Blueprint $table) {
            $table->index(['order_id', 'status']);          // items per order
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['partner_id', 'status']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'partner_id']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_default']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['driver_id', 'status']);
            $table->dropIndex(['partner_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropIndex(['status', 'paid_at']);
        });

        Schema::table('lesbuy_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
        });
    }
};
