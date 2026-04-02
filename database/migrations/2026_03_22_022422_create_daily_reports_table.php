<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_reports')) return;
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('new_drivers')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('avg_fare', 8, 2)->default(0);
            $table->unsignedInteger('total_distance_km')->default(0);
            $table->json('meta')->nullable();               // extensible extra stats
            $table->timestamps();

            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
