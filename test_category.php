<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PartnerController;

$user = User::whereHas('partner')->first();
if (!$user) {
    $partner = Partner::first();
    if (!$partner) die("No partner found\n");
    $user = User::where('id', $partner->user_id)->first();
    if (!$user) die("Partner has no user\n");
} else {
    $partner = $user->partner;
}

echo "Testing as User {$user->id} for Partner {$partner->id}\n";

// Authenticate
auth()->login($user);

// Create request
$request = App\Http\Requests\StoreMenuCategoryRequest::create(
    '/api/v1/partners/'.$partner->id.'/menu-categories', 
    'POST', 
    [
        'name' => 'Test Cat',
        'icon_emoji' => '🍔',
    ]
);
// Force validation
$request->setContainer(app())->validateResolved();

$controller = app(PartnerController::class);
try {
    $response = $controller->storeMenuCategory($partner, $request);
    echo "Success: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n";
}
