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
        Schema::table('orders', function (Blueprint $table) {
            // Add timestamp fields for new statuses
            $table->timestamp('driver_arrived_at_pickup_at')->nullable()->after('accepted_at');
            $table->timestamp('in_progress_at')->nullable()->after('driver_arrived_at_pickup_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'driver_arrived_at_pickup_at')) {
                $table->dropColumn('driver_arrived_at_pickup_at');
            }
            if (Schema::hasColumn('orders', 'in_progress_at')) {
                $table->dropColumn('in_progress_at');
            }
        });
    }
};
