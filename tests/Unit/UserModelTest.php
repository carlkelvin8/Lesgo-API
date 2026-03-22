<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    private function makeUser(string $role): User
    {
        $user       = new User();
        $user->role = $role;
        return $user;
    }

    public function test_is_customer(): void
    {
        $this->assertTrue($this->makeUser('customer')->isCustomer());
        $this->assertFalse($this->makeUser('admin')->isCustomer());
    }

    public function test_is_driver(): void
    {
        $this->assertTrue($this->makeUser('driver')->isDriver());
        $this->assertFalse($this->makeUser('customer')->isDriver());
    }

    public function test_is_admin(): void
    {
        $this->assertTrue($this->makeUser('admin')->isAdmin());
        $this->assertFalse($this->makeUser('driver')->isAdmin());
    }

    public function test_is_partner_admin(): void
    {
        $this->assertTrue($this->makeUser('partner_admin')->isPartnerAdmin());
        $this->assertFalse($this->makeUser('customer')->isPartnerAdmin());
    }

    public function test_has_role(): void
    {
        $user = $this->makeUser('driver');
        $this->assertTrue($user->hasRole('driver'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_has_any_role(): void
    {
        $user = $this->makeUser('partner_admin');
        $this->assertTrue($user->hasAnyRole(['admin', 'partner_admin']));
        $this->assertFalse($user->hasAnyRole(['customer', 'driver']));
    }
}
