<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            // Add columns if they don't exist
            $columns = $this->getExistingColumns('audit_logs');

            if (!in_array('event_type', $columns)) {
                $table->string('event_type')->nullable()->after('action');
            }

            if (!in_array('event_category', $columns)) {
                $table->string('event_category')->nullable()->after('event_type');
            }

            if (!in_array('resource_type', $columns)) {
                $table->string('resource_type')->nullable()->after('model');
            }

            if (!in_array('resource_id', $columns)) {
                $table->bigInteger('resource_id')->nullable()->after('model_id');
            }

            if (!in_array('old_values', $columns)) {
                $table->json('old_values')->nullable()->after('changes');
            }

            if (!in_array('new_values', $columns)) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (!in_array('session_id', $columns)) {
                $table->string('session_id')->nullable()->after('user_agent');
            }

            if (!in_array('request_id', $columns)) {
                $table->string('request_id')->nullable()->after('session_id');
            }

            if (!in_array('risk_level', $columns)) {
                $table->string('risk_level')->default('medium')->after('request_id');
            }

            if (!in_array('is_suspicious', $columns)) {
                $table->boolean('is_suspicious')->default(false)->after('risk_level');
            }

            if (!in_array('context', $columns)) {
                $table->json('context')->nullable()->after('is_suspicious');
            }

            if (!in_array('metadata', $columns)) {
                $table->json('metadata')->nullable()->after('context');
            }

            if (!in_array('occurred_at', $columns)) {
                $table->timestamp('occurred_at')->useNow()->after('created_at');
            }

            // Add indexes for performance
            if (!in_array('event_type', $columns)) {
                $table->index('event_type');
            }

            if (!in_array('event_category', $columns)) {
                $table->index('event_category');
            }

            if (!in_array('risk_level', $columns)) {
                $table->index('risk_level');
            }

            if (!in_array('is_suspicious', $columns)) {
                $table->index('is_suspicious');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $columns = $this->getExistingColumns('audit_logs');
            
            if (in_array('event_type', $columns)) $table->dropColumn('event_type');
            if (in_array('event_category', $columns)) $table->dropColumn('event_category');
            if (in_array('resource_type', $columns)) $table->dropColumn('resource_type');
            if (in_array('resource_id', $columns)) $table->dropColumn('resource_id');
            if (in_array('old_values', $columns)) $table->dropColumn('old_values');
            if (in_array('new_values', $columns)) $table->dropColumn('new_values');
            if (in_array('session_id', $columns)) $table->dropColumn('session_id');
            if (in_array('request_id', $columns)) $table->dropColumn('request_id');
            if (in_array('risk_level', $columns)) $table->dropColumn('risk_level');
            if (in_array('is_suspicious', $columns)) $table->dropColumn('is_suspicious');
            if (in_array('context', $columns)) $table->dropColumn('context');
            if (in_array('metadata', $columns)) $table->dropColumn('metadata');
            if (in_array('occurred_at', $columns)) $table->dropColumn('occurred_at');
        });
    }

    private function getExistingColumns(string $table): array
    {
        return Schema::getColumnListing($table);
    }
};
