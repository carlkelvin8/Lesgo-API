<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearOrdersData extends Command
{
    protected $signature = 'orders:clear
        {--force : Skip confirmation prompt}
        {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clear all order-related data from the database';

    public function handle(): int
    {
        if (!app()->environment('local', 'staging') && !$this->option('force')) {
            $this->warn('This command is intended for local/staging environments only.');
            $this->warn('Current environment: ' . app()->environment());
            if (!$this->confirm('Continue anyway?', false)) {
                return Command::FAILURE;
            }
        }

        $steps = [
            'customer_satisfaction_surveys' => function () {
                return DB::table('customer_satisfaction_surveys')->count();
            },
            'lesbuy_items' => function () {
                return DB::table('lesbuy_items')->count();
            },
            'order_tracking_events' => function () {
                return DB::table('order_tracking_events')->count();
            },
            'orders' => function () {
                return DB::table('orders')->count();
            },
            'payments (order-related)' => function () {
                return DB::table('payments')->whereNotNull('order_id')->count();
            },
            'realtime_notifications' => function () {
                return DB::table('realtime_notifications')->count();
            },
            'notifications (order.*)' => function () {
                return DB::table('notifications')->where('type', 'like', 'order.%')->count();
            },
            'daily_reports' => function () {
                return DB::table('daily_reports')->count();
            },
        ];

        $this->info('Current record counts:');
        $total = 0;
        foreach ($steps as $label => $counter) {
            $count = $counter();
            $total += $count;
            $this->line("  {$label}: {$count}");
        }

        if ($total === 0) {
            $this->info('No order data to clear.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run — no data was deleted.');
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            $confirmed = $this->ask('Type "DELETE" to confirm permanent deletion');
            if ($confirmed !== 'DELETE') {
                $this->info('Cancelled.');
                return Command::FAILURE;
            }
        }

        $bar = $this->output->createProgressBar(count($steps));
        $bar->start();

        // Order-dependent tables first, then orders, then rest
        DB::statement('TRUNCATE TABLE customer_satisfaction_surveys RESTART IDENTITY CASCADE');
        $bar->advance();

        DB::statement('TRUNCATE TABLE lesbuy_items RESTART IDENTITY CASCADE');
        $bar->advance();

        DB::statement('TRUNCATE TABLE order_tracking_events RESTART IDENTITY CASCADE');
        $bar->advance();

        DB::statement('TRUNCATE TABLE orders RESTART IDENTITY CASCADE');
        $bar->advance();

        // Shared tables — only remove order-related rows
        DB::table('payments')->whereNotNull('order_id')->delete();
        $bar->advance();

        DB::statement('TRUNCATE TABLE realtime_notifications RESTART IDENTITY CASCADE');
        $bar->advance();

        DB::table('notifications')->where('type', 'like', 'order.%')->delete();
        $bar->advance();

        DB::statement('TRUNCATE TABLE daily_reports RESTART IDENTITY CASCADE');
        $bar->advance();

        $bar->finish();
        $this->newLine(2);
        $this->info('All order data has been cleared.');

        return Command::SUCCESS;
    }
}
