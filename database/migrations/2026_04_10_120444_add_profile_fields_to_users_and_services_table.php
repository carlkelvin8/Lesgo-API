<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing profile fields to users
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->string('referral_code', 20)->nullable()->unique();
            $table->string('referred_by', 20)->nullable();
            $table->integer('points')->default(0);
        });

        // Add missing fields to services
        Schema::table('services', function (Blueprint $table) {
            $table->string('icon_url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->nullable(); // delivery, errand, food, etc.
            $table->json('features')->nullable();    // list of feature strings
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'address_line1',
                'address_line2',
                'profile_photo_url',
                'referral_code',
                'referred_by',
                'points',
            ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['icon_url', 'image_url', 'category', 'features', 'sort_order']);
        });
    }
};
