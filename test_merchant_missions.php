<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'merchant@test.com')->first();
$partnerId = $user->partner->id;
$today = \Carbon\Carbon::now()->toDateString();

$templates = App\Models\MissionTemplate::where('is_active', true)
    ->where('target_audience', 'merchant')
    ->where('type', 'daily')
    ->get();

$missions = [];
foreach ($templates as $template) {
    $merchantMission = App\Models\MerchantMission::firstOrCreate(
        [
            'partner_id' => $partnerId,
            'mission_template_id' => $template->id,
            'mission_date' => $today,
        ],
        [
            'current_progress' => 0,
            'goal_target' => $template->goal_target,
            'is_completed' => false,
        ]
    );

    $missions[] = [
        'id' => $merchantMission->id,
        'title' => $template->title,
    ];
}

echo json_encode($missions);
