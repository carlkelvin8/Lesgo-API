<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans table
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_price_id')->unique();
            $table->decimal('price', 10, 2);
            $table->string('interval'); // month, year
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->unique()->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('status'); // active, trialing, past_due, canceled, etc.
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Applicant profiles table
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('position_applied')->nullable();
            $table->json('role_tags')->nullable();
            $table->string('headline')->nullable();
            $table->string('location')->nullable();
            $table->text('experience')->nullable();
            $table->text('education')->nullable();
            $table->json('skills')->nullable();
            $table->timestamps();
        });

        // Resumes table
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicant_profiles')->onDelete('cascade');
            $table->string('file_path');
            $table->string('original_filename');
            $table->text('parsed_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
        Schema::dropIfExists('applicant_profiles');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
