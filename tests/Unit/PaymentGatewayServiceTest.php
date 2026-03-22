<?php

namespace Tests\Unit;

use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests — no DB, no HTTP.
 * Boots a minimal Laravel app just for config().
 */
class PaymentGatewayServiceTest extends TestCase
{
    private static ?Application $app = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$app) {
            self::$app = require __DIR__ . '/../../bootstrap/app.php';
            self::$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
    }

    public function test_webhook_token_passes_when_not_configured(): void
    {
        config(['services.xendit.webhook_token' => '']);
        config(['services.xendit.secret_key' => '']);

        $service = new PaymentGatewayService();
        $this->assertTrue($service->verifyWebhookToken('anything'));
    }

    public function test_webhook_token_passes_with_correct_token(): void
    {
        config(['services.xendit.webhook_token' => 'my-secret-token']);
        config(['services.xendit.secret_key' => '']);

        $service = new PaymentGatewayService();
        $this->assertTrue($service->verifyWebhookToken('my-secret-token'));
    }

    public function test_webhook_token_fails_with_wrong_token(): void
    {
        config(['services.xendit.webhook_token' => 'my-secret-token']);
        config(['services.xendit.secret_key' => '']);

        $service = new PaymentGatewayService();
        $this->assertFalse($service->verifyWebhookToken('wrong-token'));
    }
}
