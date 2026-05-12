<?php
/**
 * Seed mission templates for the driver missions system.
 * Run: php seed_mission_templates.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MissionTemplate;

$templates = [
    [
        'title' => 'Complete 3 Deliveries',
        'description' => 'Complete 3 orders today to earn your reward.',
        'type' => 'daily',
        'goal_type' => 'complete_orders',
        'goal_target' => 3,
        'reward_amount' => 50.00,
        'reward_currency' => 'PHP',
        'is_active' => true,
        'service_code' => null,
    ],
    [
        'title' => 'Complete 5 Deliveries',
        'description' => 'Complete 5 orders today for a bigger reward.',
        'type' => 'daily',
        'goal_type' => 'complete_orders',
        'goal_target' => 5,
        'reward_amount' => 100.00,
        'reward_currency' => 'PHP',
        'is_active' => true,
        'service_code' => null,
    ],
    [
        'title' => 'LesEat Specialist',
        'description' => 'Complete 2 LesEat food delivery orders today.',
        'type' => 'daily',
        'goal_type' => 'specific_service',
        'goal_target' => 2,
        'reward_amount' => 40.00,
        'reward_currency' => 'PHP',
        'is_active' => true,
        'service_code' => 'LESEAT',
    ],
    [
        'title' => 'LesBuy Champion',
        'description' => 'Complete 2 LesBuy shopping orders today.',
        'type' => 'daily',
        'goal_type' => 'specific_service',
        'goal_target' => 2,
        'reward_amount' => 40.00,
        'reward_currency' => 'PHP',
        'is_active' => true,
        'service_code' => 'LESBUY',
    ],
    [
        'title' => 'Top Rated Driver',
        'description' => 'Receive a 5-star rating from a customer today.',
        'type' => 'daily',
        'goal_type' => 'get_rating',
        'goal_target' => 1,
        'reward_amount' => 30.00,
        'reward_currency' => 'PHP',
        'is_active' => true,
        'service_code' => null,
    ],
];

$created = 0;
foreach ($templates as $template) {
    $existing = MissionTemplate::where('title', $template['title'])->first();
    if (!$existing) {
        MissionTemplate::create($template);
        $created++;
        echo "✅ Created: {$template['title']}\n";
    } else {
        echo "⏭️  Already exists: {$template['title']}\n";
    }
}

echo "\n✅ Done! Created $created mission templates.\n";
