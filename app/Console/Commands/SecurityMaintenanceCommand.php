<?php

namespace App\Console\Commands;

use App\Services\SecurityService;
use App\Services\BiometricAuthService;
use App\Services\GdprService;
use App\Models\SecurityEvent;
use App\Models\IpBlacklist;
use App\Models\IpWhitelist;
use Illuminate\Console\Command;

class SecurityMaintenanceCommand extends Command
{
    protected $signature = 'security:maintenance {--cleanup-expired : Clean up expired entries}';
    protected $description = 'Perform security maintenance tasks';

    private SecurityService $securityService;
    private BiometricAuthService $biometricService;
    private GdprService $gdprService;

    public function __construct(
        SecurityService $securityService,
        BiometricAuthService $biometricService,
        GdprService $gdprService
    ) {
        parent::__construct();
        $this->securityService = $securityService;
        $this->biometricService = $biometricService;
        $this->gdprService = $gdprService;
    }

    public function handle(): int
    {
        $this->info('Starting security maintenance...');

        if ($this->option('cleanup-expired')) {
            $this->cleanupExpiredEntries();
        }

        $this->cleanupExpiredBiometrics();
        $this->applyDataRetentionPolicies();
        $this->generateSecurityReport();

        $this->info('Security maintenance completed successfully.');
        return 0;
    }

    private function cleanupExpiredEntries(): void
    {
        $this->info('Cleaning up expired entries...');

        // Clean up expired IP whitelist entries
        $expiredWhitelist = IpWhitelist::where('is_active', true)
            ->where('expires_at', '<', now())
            ->count();

        if ($expiredWhitelist > 0) {
            IpWhitelist::where('is_active', true)
                ->where('expires_at', '<', now())
                ->update(['is_active' => false]);
            
            $this->info("Deactivated {$expiredWhitelist} expired whitelist entries");
        }

        // Clean up expired IP blacklist entries
        $expiredBlacklist = IpBlacklist::where('is_active', true)
            ->where('expires_at', '<', now())
            ->count();

        if ($expiredBlacklist > 0) {
            IpBlacklist::where('is_active', true)
                ->where('expires_at', '<', now())
                ->update(['is_active' => false]);
            
            $this->info("Deactivated {$expiredBlacklist} expired blacklist entries");
        }
    }

    private function cleanupExpiredBiometrics(): void
    {
        $this->info('Cleaning up expired biometric authentications...');
        
        $count = $this->biometricService->cleanupExpiredBiometrics();
        
        if ($count > 0) {
            $this->info("Deactivated {$count} expired biometric authentications");
        } else {
            $this->info('No expired biometric authentications found');
        }
    }

    private function applyDataRetentionPolicies(): void
    {
        $this->info('Applying data retention policies...');
        
        $results = $this->gdprService->applyRetentionPolicies();
        
        foreach ($results as $dataType => $count) {
            if ($count > 0) {
                $this->info("Applied retention policy for {$dataType}: {$count} records processed");
            }
        }
    }

    private function generateSecurityReport(): void
    {
        $this->info('Generating security report...');
        
        $dashboard = $this->securityService->getSecurityDashboard();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Recent Events (24h)', $dashboard['recent_events']],
                ['Critical Unresolved Events', $dashboard['critical_events']],
                ['Failed Logins (24h)', $dashboard['failed_logins']],
                ['Suspicious Activities (24h)', $dashboard['suspicious_activities']],
                ['Blocked IPs', $dashboard['blocked_ips']],
                ['Whitelisted IPs', $dashboard['whitelisted_ips']],
            ]
        );

        // Show critical events that need attention
        $criticalEvents = SecurityEvent::critical()->unresolved()->limit(5)->get();
        
        if ($criticalEvents->isNotEmpty()) {
            $this->warn('Critical security events requiring attention:');
            
            foreach ($criticalEvents as $event) {
                $this->line("- [{$event->id}] {$event->event_type}: {$event->description}");
            }
        }
    }
}