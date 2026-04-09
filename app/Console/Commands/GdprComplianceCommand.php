<?php

namespace App\Console\Commands;

use App\Services\GdprService;
use App\Models\GdprRequest;
use Illuminate\Console\Command;

class GdprComplianceCommand extends Command
{
    protected $signature = 'gdpr:process {--auto : Automatically process eligible requests}';
    protected $description = 'Process GDPR compliance requests';

    private GdprService $gdprService;

    public function __construct(GdprService $gdprService)
    {
        parent::__construct();
        $this->gdprService = $gdprService;
    }

    public function handle(): int
    {
        $this->info('Processing GDPR compliance requests...');

        $this->showComplianceStatus();
        
        if ($this->option('auto')) {
            $this->processEligibleRequests();
        } else {
            $this->processInteractively();
        }

        $this->cleanupExpiredExports();

        $this->info('GDPR compliance processing completed.');
        return 0;
    }

    private function showComplianceStatus(): void
    {
        $status = $this->gdprService->getComplianceStatus();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending Requests', $status['pending_requests']],
                ['Processing Requests', $status['processing_requests']],
                ['Completed (Last Month)', $status['completed_requests']],
                ['Active Policies', $status['active_policies']],
                ['Expired Exports', $status['expired_exports']],
            ]
        );
    }

    private function processEligibleRequests(): void
    {
        // Process verified access requests automatically
        $accessRequests = GdprRequest::where('status', 'processing')
            ->where('request_type', 'access')
            ->whereNotNull('verified_at')
            ->get();

        foreach ($accessRequests as $request) {
            try {
                $this->gdprService->processDataAccess($request);
                $this->info("Processed access request #{$request->id} for user {$request->user->email}");
            } catch (\Exception $e) {
                $this->error("Failed to process access request #{$request->id}: {$e->getMessage()}");
            }
        }

        // Note: Erasure requests should be processed manually for safety
        $erasureRequests = GdprRequest::where('status', 'processing')
            ->where('request_type', 'erasure')
            ->whereNotNull('verified_at')
            ->count();

        if ($erasureRequests > 0) {
            $this->warn("{$erasureRequests} erasure requests require manual processing for safety.");
        }
    }

    private function processInteractively(): void
    {
        $pendingRequests = GdprRequest::where('status', 'processing')
            ->whereNotNull('verified_at')
            ->with('user')
            ->get();

        if ($pendingRequests->isEmpty()) {
            $this->info('No pending GDPR requests to process.');
            return;
        }

        foreach ($pendingRequests as $request) {
            $this->line('');
            $this->info("GDPR Request #{$request->id}");
            $this->line("Type: {$request->request_type}");
            $this->line("User: {$request->user->email}");
            $this->line("Created: {$request->created_at->format('Y-m-d H:i:s')}");
            $this->line("Description: {$request->description}");

            if ($request->request_type === 'access') {
                if ($this->confirm('Process this data access request?')) {
                    try {
                        $this->gdprService->processDataAccess($request);
                        $this->info("✓ Access request processed successfully");
                    } catch (\Exception $e) {
                        $this->error("✗ Failed to process request: {$e->getMessage()}");
                    }
                }
            } elseif ($request->request_type === 'erasure') {
                $this->warn('⚠️  ERASURE REQUEST - This will permanently delete/anonymize user data!');
                if ($this->confirm('Are you absolutely sure you want to process this erasure request?')) {
                    if ($this->confirm('This action cannot be undone. Confirm erasure?')) {
                        try {
                            $this->gdprService->processDataErasure($request);
                            $this->info("✓ Erasure request processed successfully");
                        } catch (\Exception $e) {
                            $this->error("✗ Failed to process erasure: {$e->getMessage()}");
                        }
                    }
                }
            } else {
                $this->line("Manual processing required for {$request->request_type} requests");
                
                $notes = $this->ask('Enter processing notes (optional)');
                
                if ($this->confirm('Mark this request as completed?')) {
                    $request->complete('console_admin', $notes);
                    $this->info("✓ Request marked as completed");
                }
            }
        }
    }

    private function cleanupExpiredExports(): void
    {
        $expiredExports = GdprRequest::whereNotNull('export_file_path')
            ->where('export_expires_at', '<', now())
            ->get();

        foreach ($expiredExports as $request) {
            // Delete the export file
            if (\Storage::disk('local')->exists($request->export_file_path)) {
                \Storage::disk('local')->delete($request->export_file_path);
            }

            // Clear the export path
            $request->update([
                'export_file_path' => null,
                'export_expires_at' => null,
            ]);

            $this->info("Cleaned up expired export for request #{$request->id}");
        }
    }
}