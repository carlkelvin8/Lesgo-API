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
        Schema::table('mission_templates', function (Blueprint $table) {
            $table->string('target_audience')->default('driver')->after('type'); // driver, merchant, customer
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mission_templates', function (Blueprint $table) {
            $table->dropColumn('target_audience');
        });
    }
};
