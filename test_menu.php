<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$partnerId = 9; // Assuming partner 9
$controller = app(App\Http\Controllers\Api\PartnerController::class);

$request = Illuminate\Http\Request::create('/api/v1/partners/'.$partnerId.'/menu', 'GET');
try {
    $partner = App\Models\Partner::find($partnerId);
    if (!$partner) {
        $partner = App\Models\Partner::first();
    }
    echo "Testing for Partner: " . $partner->id . "\n";
    $response = $controller->menu($partner);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n";
}
