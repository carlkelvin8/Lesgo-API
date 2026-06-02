<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_satisfaction_surveys')) {
            return;
        }

        Schema::create('customer_satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->unsignedInteger('completed_orders_count')->default(0);
            $table->string('source', 64)->default('customer_app');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_satisfaction_surveys');
    }
};
