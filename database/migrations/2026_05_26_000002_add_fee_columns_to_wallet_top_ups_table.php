<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_top_ups', function (Blueprint $table) {
            $table->decimal('fee', 12, 2)->default(0)->after('amount');
            $table->decimal('total_charged', 12, 2)->nullable()->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_top_ups', function (Blueprint $table) {
            $table->dropColumn(['fee', 'total_charged']);
        });
    }
};
