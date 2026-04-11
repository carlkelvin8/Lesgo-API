<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // LesGo delivery-specific fields
            $table->string('item_description')->nullable();    // "Books, Clothes, Electronics"
            $table->decimal('estimated_weight_kg', 8, 2)->nullable();

            // Fare breakdown stored for receipt display
            $table->json('fare_breakdown')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['item_description', 'estimated_weight_kg', 'fare_breakdown']);
        });
    }
};
