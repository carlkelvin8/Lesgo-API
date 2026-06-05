<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('driver_profiles')) {
            return;
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_profiles', 'is_available')) {
                $table->boolean('is_available')->default(false)->after('status');
            }
        });

        if (Schema::hasTable('driver_duty_attendances')) {
            return;
        }

        Schema::create('driver_duty_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('photo_path');
            $table->boolean('on_duty')->default(true);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['driver_profile_id', 'captured_at']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_duty_attendances');

        if (!Schema::hasTable('driver_profiles')) {
            return;
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('driver_profiles', 'is_available')) {
                $table->dropColumn('is_available');
            }
        });
    }
};
