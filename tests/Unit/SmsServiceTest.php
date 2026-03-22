<?php

namespace Tests\Unit;

use App\Services\SmsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SmsServiceTest extends TestCase
{
    private function callPrivate(SmsService $service, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionClass($service);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($service, ...$args);
    }

    public function test_normalizes_ph_local_number(): void
    {
        $sms    = new SmsService();
        $result = $this->callPrivate($sms, 'normalizePhone', '09171234567');
        $this->assertEquals('+639171234567', $result);
    }

    public function test_normalizes_number_without_plus(): void
    {
        $sms    = new SmsService();
        $result = $this->callPrivate($sms, 'normalizePhone', '639171234567');
        $this->assertEquals('+639171234567', $result);
    }

    public function test_leaves_e164_number_unchanged(): void
    {
        $sms    = new SmsService();
        $result = $this->callPrivate($sms, 'normalizePhone', '+639171234567');
        $this->assertEquals('+639171234567', $result);
    }

    public function test_detects_philippine_number(): void
    {
        $sms = new SmsService();
        $this->assertTrue($this->callPrivate($sms, 'isPhilippineNumber', '+639171234567'));
        $this->assertFalse($this->callPrivate($sms, 'isPhilippineNumber', '+12025551234'));
    }
}
