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
        Schema::create('merchant_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_template_id')->constrained()->cascadeOnDelete();
            $table->date('mission_date');
            $table->integer('current_progress')->default(0);
            $table->integer('goal_target');
            $table->boolean('is_completed')->default(false);
            $table->boolean('reward_claimed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // A merchant can only have one instance of a specific mission per day
            $table->unique(['partner_id', 'mission_template_id', 'mission_date'], 'merch_mission_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_missions');
    }
};
