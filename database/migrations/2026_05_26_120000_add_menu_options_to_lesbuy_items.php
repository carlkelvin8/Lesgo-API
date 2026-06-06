<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesbuy_items', function (Blueprint $table) {
            if (!Schema::hasColumn('lesbuy_items', 'menu_item_id')) {
                $table->foreignId('menu_item_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('menu_items')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('lesbuy_items', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lesbuy_items', function (Blueprint $table) {
            if (Schema::hasColumn('lesbuy_items', 'menu_item_id')) {
                $table->dropConstrainedForeignId('menu_item_id');
            }
            if (Schema::hasColumn('lesbuy_items', 'selected_options')) {
                $table->dropColumn('selected_options');
            }
        });
    }
};
