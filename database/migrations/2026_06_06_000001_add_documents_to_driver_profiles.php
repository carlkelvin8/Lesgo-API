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
            if (!Schema::hasColumn('driver_profiles', 'documents')) {
                $table->json('documents')->nullable()->after('id_document_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('driver_profiles')) {
            return;
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('driver_profiles', 'documents')) {
                $table->dropColumn('documents');
            }
        });
    }
};
