<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('name');
            $table->string('cover_image_url')->nullable()->after('logo_url');
            $table->text('description')->nullable()->after('cover_image_url');
            $table->string('category')->nullable()->after('description'); // restaurant, grocery, pharmacy, bakery, etc.
            $table->json('tags')->nullable()->after('category');           // ["fast food", "chicken", "burgers"]
            $table->json('cuisine_types')->nullable()->after('tags');      // ["Filipino", "American"]
            $table->decimal('rating', 3, 2)->default(0.00)->after('cuisine_types');
            $table->unsignedInteger('total_reviews')->default(0)->after('rating');
            $table->decimal('delivery_fee', 8, 2)->default(0.00)->after('total_reviews');
            $table->unsignedInteger('min_order_amount')->default(0)->after('delivery_fee');
            $table->unsignedInteger('estimated_delivery_minutes')->default(30)->after('min_order_amount');
            $table->boolean('is_open')->default(true)->after('estimated_delivery_minutes');
            $table->boolean('is_featured')->default(false)->after('is_open');
            $table->boolean('accepts_online_payment')->default(true)->after('is_featured');
            $table->json('opening_hours')->nullable()->after('accepts_online_payment'); // {"mon":{"open":"08:00","close":"22:00"}, ...}
        });

        Schema::table('partner_branches', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('name');
            $table->boolean('is_open')->default(true)->after('is_primary');
            $table->unsignedInteger('estimated_delivery_minutes')->default(30)->after('is_open');
            $table->decimal('delivery_fee', 8, 2)->nullable()->after('estimated_delivery_minutes'); // overrides partner default
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
