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
        // Partners
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->string('business_type')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // Driver Profiles
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->string('status')->default('pending');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_trips')->default(0);
            $table->string('license_number')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('per_km_rate', 10, 2)->default(0);
            $table->decimal('per_minute_rate', 10, 2)->default(0);
            $table->decimal('minimum_fare', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Addresses
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('label')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->foreignId('driver_id')->nullable()->constrained('driver_profiles'); // Note: referencing driver_profiles, not users
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('pickup_address_id')->nullable()->constrained('addresses');
            $table->foreignId('dropoff_address_id')->nullable()->constrained('addresses');
            
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->integer('estimated_distance_m')->default(0);
            $table->integer('actual_distance_m')->nullable();
            
            $table->decimal('estimated_fare', 10, 2)->default(0);
            $table->decimal('actual_fare', 10, 2)->nullable();
            $table->decimal('partner_share', 10, 2)->default(0);
            $table->decimal('driver_share', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('cancel_reason')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamps();
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('partner_id')->nullable()->constrained('partners');
            $table->foreignId('driver_id')->nullable()->constrained('driver_profiles');
            
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('PHP');
            $table->string('method')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('services');
        Schema::dropIfExists('driver_profiles');
        Schema::dropIfExists('partners');
    }
};
