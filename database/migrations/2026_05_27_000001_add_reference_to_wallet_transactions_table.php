<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `reference` column that WalletController selects.
 * Previously missing from the original migration, causing 500 on /wallets/my/transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('wallet_transactions', 'reference')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->string('reference')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wallet_transactions', 'reference')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropColumn('reference');
            });
        }
    }
};
