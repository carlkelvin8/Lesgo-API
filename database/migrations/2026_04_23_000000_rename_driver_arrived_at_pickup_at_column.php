<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rename the malformed column 'driver_arrived_at_pickup_at' to 'driver_arrived_at_pickup'
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'driver_arrived_at_pickup_at')) {
                $table->renameColumn('driver_arrived_at_pickup_at', 'driver_arrived_at_pickup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'driver_arrived_at_pickup')) {
                $table->renameColumn('driver_arrived_at_pickup', 'driver_arrived_at_pickup_at');
            }
        });
    }
};
