<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserRolesTest extends TestCase
{
    public function testExistingUserAndAdminRolesRemainValid(): void
    {
        $user = new User();
        $user->setRole('user');
        self::assertSame(['ROLE_USER'], $user->getRoles());

        $admin = new User();
        $admin->setRole('admin');
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $admin->getRoles());
    }

    public function testStaffRoleCannotBecomeAdminAndStillHasUserAccess(): void
    {
        $staff = new User();
        $staff->setRole('staff');

        self::assertSame(['ROLE_STAFF', 'ROLE_USER'], $staff->getRoles());
        self::assertNotContains('ROLE_ADMIN', $staff->getRoles());
    }
}
