<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('logo_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // restaurant, grocery, pharmacy, bakery, etc.
            $table->json('tags')->nullable();           // ["fast food", "chicken", "burgers"]
            $table->json('cuisine_types')->nullable();      // ["Filipino", "American"]
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->integer('min_order_amount')->default(0);
            $table->integer('estimated_delivery_minutes')->default(30);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('accepts_online_payment')->default(true);
            $table->json('opening_hours')->nullable(); // {"mon":{"open":"08:00","close":"22:00"}, ...}
        });

        Schema::table('partner_branches', function (Blueprint $table) {
            $table->string('logo_url')->nullable();
            $table->boolean('is_open')->default(true);
            $table->integer('estimated_delivery_minutes')->default(30);
            $table->decimal('delivery_fee', 8, 2)->nullable(); // overrides partner default
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url', 'cover_image_url', 'description', 'category',
                'tags', 'cuisine_types', 'rating', 'total_reviews',
                'delivery_fee', 'min_order_amount', 'estimated_delivery_minutes',
                'is_open', 'is_featured', 'accepts_online_payment', 'opening_hours',
            ]);
        });

        Schema::table('partner_branches', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'is_open', 'estimated_delivery_minutes', 'delivery_fee']);
        });
    }
};
