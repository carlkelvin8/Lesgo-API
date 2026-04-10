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
            $table->date('date_of_birth')->nullable()->after('phone_number');
            $table->string('address_line1')->nullable()->after('date_of_birth');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('profile_photo_url')->nullable()->after('address_line2');
            $table->string('referral_code', 20)->nullable()->unique()->after('profile_photo_url');
            $table->string('referred_by', 20)->nullable()->after('referral_code');
            $table->unsignedInteger('points')->default(0)->after('referred_by');
        });

        // Add missing fields to services
        Schema::table('services', function (Blueprint $table) {
            $table->string('icon_url')->nullable()->after('description');
            $table->string('image_url')->nullable()->after('icon_url');
            $table->string('category')->nullable()->after('image_url'); // delivery, errand, food, etc.
            $table->json('features')->nullable()->after('category');    // list of feature strings
            $table->integer('sort_order')->default(0)->after('features');
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
