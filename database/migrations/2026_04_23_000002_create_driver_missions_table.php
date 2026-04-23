<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mission templates (defined by admin)
        Schema::create('mission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // daily, weekly, monthly, one_time
            $table->string('goal_type'); // complete_orders, get_rating, refer_friend, specific_service
            $table->integer('goal_target'); // e.g., 10 orders, 5 stars
            $table->decimal('reward_amount', 10, 2);
            $table->string('reward_currency')->default('PHP');
            $table->string('service_code')->nullable(); // for specific_service type (lesride, leseat, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Driver mission progress (per driver)
        Schema::create('driver_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->foreignId('mission_template_id')->constrained('mission_templates')->cascadeOnDelete();
            $table->integer('current_progress')->default(0);
            $table->integer('goal_target');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('reward_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->date('mission_date'); // for daily missions
            $table->timestamps();

            // Unique constraint: one mission per driver per day
            $table->unique(['driver_profile_id', 'mission_template_id', 'mission_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_missions');
        Schema::dropIfExists('mission_templates');
    }
};
