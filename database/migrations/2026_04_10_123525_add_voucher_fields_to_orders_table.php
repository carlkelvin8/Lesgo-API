<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->after('payment_method');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('voucher_code');
            $table->string('vehicle_type')->nullable()->after('discount_amount'); // Motor, Car, Van
            $table->string('passenger_name')->nullable()->after('vehicle_type');  // for ride-hailing
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'discount_amount', 'vehicle_type', 'passenger_name']);
        });
    }
};
