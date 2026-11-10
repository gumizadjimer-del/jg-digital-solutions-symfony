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

    public function testUserEqualityChangesWhenSessionSecurityDataChanges(): void
    {
        $original = (new User())
            ->setUsername('session-user')
            ->setEmail('session-user@example.test')
            ->setPassword('hash-one')
            ->setRole('staff');

        self::assertTrue($original->isEqualTo(clone $original));

        $changedEmail = clone $original;
        $changedEmail->setEmail('renamed@example.test');
        self::assertFalse($original->isEqualTo($changedEmail));

        $changedPassword = clone $original;
        $changedPassword->setPassword('hash-two');
        self::assertFalse($original->isEqualTo($changedPassword));

        $changedRole = clone $original;
        $changedRole->setRole('user');
        self::assertFalse($original->isEqualTo($changedRole));

        $disabled = clone $original;
        $disabled->setActive(false);
        self::assertFalse($original->isEqualTo($disabled));
    }
}
