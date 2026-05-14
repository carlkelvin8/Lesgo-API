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
        Schema::create('customer_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mission_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('current_progress')->default(0);
            $table->integer('goal_target');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('reward_type')->default('voucher');
            $table->decimal('reward_value', 10, 2)->default(0);
            $table->boolean('reward_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->date('mission_date');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'mission_date']);
            $table->index(['user_id', 'mission_type', 'mission_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_missions');
    }
};
