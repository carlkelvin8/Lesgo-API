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
            if (!Schema::hasColumn('driver_profiles', 'package_tier')) {
                $table->string('package_tier', 20)
                    ->default('basic')
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('driver_profiles')) {
            return;
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('driver_profiles', 'package_tier')) {
                $table->dropColumn('package_tier');
            }
        });
    }
};
