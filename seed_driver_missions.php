<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find driver 74
$driverProfile = \App\Models\DriverProfile::find(74);

if (!$driverProfile) {
    echo "Driver 74 not found!\n";
    exit(1);
}

echo "Found driver profile: {$driverProfile->id}\n";

// Get all mission templates
$templates = \App\Models\MissionTemplate::where('is_active', true)->get();
$today = now()->toDateString();

echo "Creating missions for today: $today\n\n";

foreach ($templates as $template) {
    // Simulate some progress
    $progress = 0;
    switch ($template->goal_type) {
        case 'complete_orders':
            // Driver has 8 completed orders today (out of 10)
            $progress = $template->goal_target === 10 ? 8 : 20;
            break;
        case 'refer_friend':
            // No referrals yet
            $progress = 0;
            break;
        case 'specific_service':
            // 1 lesride completed
            $progress = 1;
            break;
        case 'get_rating':
            // No 5-star rating yet
            $progress = 0;
            break;
    }

    $isCompleted = $progress >= $template->goal_target;

    $mission = \App\Models\DriverMission::updateOrCreate(
        [
            'driver_profile_id' => $driverProfile->id,
            'mission_template_id' => $template->id,
            'mission_date' => $today,
        ],
        [
            'current_progress' => $progress,
            'goal_target' => $template->goal_target,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
            'reward_claimed' => false,
        ]
    );

    $progressPercent = round(($progress / $template->goal_target) * 100);
    $status = $isCompleted ? '✅ COMPLETED' : "⏳ {$progressPercent}%";
    
    echo "{$status} - {$template->title} ({$progress}/{$template->goal_target})\n";
}

echo "\n✅ Driver missions seeded successfully!\n";
