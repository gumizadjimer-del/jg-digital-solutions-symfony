<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StaffRecord;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StaffAndAccountManagementTest extends WebTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $recordIds = [];

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($this->recordIds as $id) {
                $record = $entityManager->getRepository(StaffRecord::class)->find($id);
                if ($record instanceof StaffRecord) {
                    $entityManager->remove($record);
                }
            }
            foreach ($this->userIds as $id) {
                $user = $entityManager->getRepository(User::class)->find($id);
                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }
            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAdminCanCreateAndEditStaffAndAdminAccounts(): void
    {
        $client = static::createClient();
        $admin = $this->persistUser('admin', 'admin');
        $client->loginUser($admin);

        $staff = $this->createAccountThroughAdmin($client, 'staff', 'staff');
        self::assertSame('staff', $staff->getRole());
        self::assertTrue($staff->isActive());
        self::assertTrue(password_verify('TempPass123!', (string) $staff->getPassword()));
        $staffId = (int) $staff->getId();
        $staffUsername = (string) $staff->getUsername();
        $staffEmail = (string) $staff->getEmail();

        $newAdmin = $this->createAccountThroughAdmin($client, 'admincreated', 'admin');
        self::assertSame('admin', $newAdmin->getRole());
        self::assertTrue(password_verify('TempPass123!', (string) $newAdmin->getPassword()));

        $editCrawler = $client->request('GET', '/admin/users/' . $staffId . '/edit');
        self::assertResponseIsSuccessful();
        $editForm = $editCrawler->selectButton('SAVE CHANGES')->form();
        $editForm['username'] = $staffUsername;
        $editForm['email'] = $staffEmail;
        $editForm['role'] = 'staff';
        $editForm['is_active'] = '0';
        $editForm['new_password'] = 'ResetPass123!';
        $client->submit($editForm);
        self::assertResponseRedirects('/admin/users');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $updatedStaff = $entityManager->getRepository(User::class)->find($staffId);
        self::assertInstanceOf(User::class, $updatedStaff);
        self::assertFalse($updatedStaff->isActive());
        self::assertTrue(password_verify('ResetPass123!', (string) $updatedStaff->getPassword()));
    }

    public function testDisabledAccountCannotAuthenticate(): void
    {
        $client = static::createClient();
        $disabledUser = $this->persistUser('disabled', 'staff', false);
        $email = (string) $disabledUser->getEmail();

        $loginCrawler = $client->request('GET', '/login');
        $loginForm = $loginCrawler->selectButton('LOGIN')->form();
        $loginForm['_username'] = $email;
        $loginForm['_password'] = 'TestPassword123!';
        $client->submit($loginForm);
        self::assertResponseRedirects('/login');
        $client->request('GET', '/staff');
        self::assertResponseRedirects('/login');
    }

    public function testAccountOwnerCanUpdateProfileAndChangePasswordWithoutChangingRole(): void
    {
        $client = static::createClient();
        $staff = $this->persistUser('profile', 'staff');
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/profile', [
            '_token' => $token,
            'username' => 'updated_' . bin2hex(random_bytes(4)),
            'email' => 'updated_' . bin2hex(random_bytes(4)) . '@example.test',
            'role' => 'admin',
        ]);
        self::assertResponseRedirects('/profile');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $staffId = (int) $staff->getId();
        $updatedStaff = $entityManager->getRepository(User::class)->find($staffId);
        self::assertInstanceOf(User::class, $updatedStaff);
        self::assertSame('staff', $updatedStaff->getRole());
        self::assertStringStartsWith('updated_', (string) $updatedStaff->getUsername());

        $passwordCrawler = $client->request('GET', '/profile/password');
        $passwordForm = $passwordCrawler->selectButton('CHANGE PASSWORD')->form();
        $passwordForm['current_password'] = 'TestPassword123!';
        $passwordForm['new_password'] = 'NewPassword456!';
        $passwordForm['confirm_password'] = 'NewPassword456!';
        $client->submit($passwordForm);
        self::assertResponseRedirects('/profile/password');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $updatedStaff = $entityManager->getRepository(User::class)->find($staffId);
        self::assertInstanceOf(User::class, $updatedStaff);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($updatedStaff, 'NewPassword456!'));
        self::assertSame('staff', $updatedStaff->getRole());
    }

    public function testStaffCanManageOnlyTheirOwnRecords(): void
    {
        $client = static::createClient();
        $owner = $this->persistUser('owner', 'staff');
        $ownerId = (int) $owner->getId();
        $client->loginUser($owner);

        $client->request('GET', '/staff');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Staff Dashboard');

        $crawler = $client->request('GET', '/staff/records/new');
        $form = $crawler->selectButton('SAVE RECORD')->form();
        $form['title'] = 'Owner Record ' . bin2hex(random_bytes(3));
        $form['description'] = 'Work record owned by the signed-in staff member.';
        $form['status'] = 'In Progress';
        $client->submit($form);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $record = $entityManager->getRepository(StaffRecord::class)->findOneBy(['createdBy' => $entityManager->getRepository(User::class)->find($ownerId)]);
        self::assertInstanceOf(StaffRecord::class, $record);
        $recordId = (int) $record->getId();
        $this->recordIds[] = $recordId;
        self::assertSame($ownerId, $record->getCreatedBy()?->getId());

        $editCrawler = $client->request('GET', '/staff/records/' . $recordId . '/edit');
        $editForm = $editCrawler->selectButton('SAVE RECORD')->form();
        $editForm['title'] = 'Updated owner work record';
        $editForm['description'] = 'The owner can update their own record.';
        $editForm['status'] = 'Completed';
        $client->submit($editForm);
        self::assertResponseRedirects('/staff/records/' . $recordId);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $updatedRecord = $entityManager->getRepository(StaffRecord::class)->find($recordId);
        self::assertInstanceOf(StaffRecord::class, $updatedRecord);
        self::assertSame('Completed', $updatedRecord->getStatus());

        $showCrawler = $client->request('GET', '/staff/records/' . $recordId);
        $deleteForm = $showCrawler->selectButton('DELETE')->form();
        $client->submit($deleteForm);
        self::assertResponseRedirects('/staff/records');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(StaffRecord::class)->find($recordId));
    }

    public function testStaffCannotAccessAnotherOwnersRecordsOrAdminRoutes(): void
    {
        $client = static::createClient();
        $owner = $this->persistUser('recordowner', 'staff');
        $otherStaff = $this->persistUser('recordother', 'staff');
        $record = $this->persistRecord($owner, 'Private staff record');
        $recordId = (int) $record->getId();
        $client->loginUser($otherStaff);

        $client->request('GET', '/staff/records/' . $recordId . '/edit');
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/staff/records/' . $recordId . '/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotAccessStaffDashboard(): void
    {
        $client = static::createClient();
        $user = $this->persistUser('regular', 'user');
        $client->loginUser($user);
        $client->request('GET', '/staff');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanSearchCreateEditAndDeleteAnyStaffRecord(): void
    {
        $client = static::createClient();
        $admin = $this->persistUser('recordadmin', 'admin');
        $staff = $this->persistUser('recordstaff', 'staff');
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/records/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('SAVE RECORD')->form();
        $uniqueTitle = 'Admin Managed ' . bin2hex(random_bytes(3));
        $form['title'] = $uniqueTitle;
        $form['description'] = 'Created by an administrator for the system-wide record list.';
        $form['status'] = 'On Hold';
        $client->submit($form);
        self::assertResponseRedirects('/admin/records');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $record = $entityManager->getRepository(StaffRecord::class)->findOneBy(['title' => $uniqueTitle]);
        self::assertInstanceOf(StaffRecord::class, $record);
        $recordId = (int) $record->getId();
        $this->recordIds[] = $recordId;
        self::assertSame($admin->getId(), $record->getCreatedBy()?->getId());

        $staffTitle = 'Staff Record ' . bin2hex(random_bytes(3));
        $now = new \DateTimeImmutable();
        $staff = $entityManager->getRepository(User::class)->find((int) $staff->getId());
        self::assertInstanceOf(User::class, $staff);
        $staffRecord = (new StaffRecord())
            ->setTitle($staffTitle)
            ->setDescription('A record owned by a Staff account.')
            ->setStatus('On Hold')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setCreatedBy($staff);
        $entityManager->persist($staffRecord);
        $entityManager->flush();
        $staffRecordId = (int) $staffRecord->getId();
        $this->recordIds[] = $staffRecordId;

        $staffListCrawler = $client->request('GET', '/admin/records?q=' . rawurlencode($staffTitle));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($staffTitle, (string) $client->getResponse()->getContent());
        $staffEditCrawler = $client->request('GET', '/admin/records/' . $staffRecordId . '/edit');
        $staffEditForm = $staffEditCrawler->selectButton('SAVE RECORD')->form();
        $staffEditForm['title'] = $staffTitle . ' Updated by Admin';
        $staffEditForm['description'] = 'Admin can edit a record created by Staff.';
        $staffEditForm['status'] = 'Completed';
        $client->submit($staffEditForm);
        self::assertResponseRedirects('/admin/records');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $updatedStaffRecord = $entityManager->getRepository(StaffRecord::class)->find($staffRecordId);
        self::assertInstanceOf(StaffRecord::class, $updatedStaffRecord);
        self::assertSame('Completed', $updatedStaffRecord->getStatus());

        $staffListCrawler = $client->request('GET', '/admin/records?q=' . rawurlencode($staffTitle . ' Updated by Admin'));
        $staffDeleteForm = $staffListCrawler->filter('form[action="/admin/records/' . $staffRecordId . '/delete"]')->form();
        $client->submit($staffDeleteForm);
        self::assertResponseRedirects('/admin/records');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(StaffRecord::class)->find($staffRecordId));

        $listCrawler = $client->request('GET', '/admin/records?q=' . rawurlencode($uniqueTitle) . '&status=On%20Hold');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($uniqueTitle, (string) $client->getResponse()->getContent());

        $editCrawler = $client->request('GET', '/admin/records/' . $recordId . '/edit');
        $editForm = $editCrawler->selectButton('SAVE RECORD')->form();
        $editForm['title'] = $uniqueTitle . ' Updated';
        $editForm['description'] = 'Updated by the administrator.';
        $editForm['status'] = 'Completed';
        $client->submit($editForm);
        self::assertResponseRedirects('/admin/records');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $updatedRecord = $entityManager->getRepository(StaffRecord::class)->find($recordId);
        self::assertInstanceOf(StaffRecord::class, $updatedRecord);
        self::assertSame('Completed', $updatedRecord->getStatus());

        $listCrawler = $client->request('GET', '/admin/records?q=' . rawurlencode($uniqueTitle . ' Updated'));
        $deleteForm = $listCrawler->filter('form[action="/admin/records/' . $recordId . '/delete"]')->form();
        $client->submit($deleteForm);
        self::assertResponseRedirects('/admin/records');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(StaffRecord::class)->find($recordId));
    }

    private function createAccountThroughAdmin($client, string $prefix, string $role): User
    {
        $crawler = $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('CREATE ACCOUNT')->form();
        $suffix = bin2hex(random_bytes(4));
        $form['username'] = $prefix . '_' . $suffix;
        $form['email'] = $prefix . '_' . $suffix . '@example.test';
        $form['role'] = $role;
        $form['password'] = 'TempPass123!';
        $client->submit($form);
        self::assertResponseRedirects('/admin/users');

        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $prefix . '_' . $suffix . '@example.test']);
        self::assertInstanceOf(User::class, $user);
        $this->userIds[] = (int) $user->getId();

        return $user;
    }

    private function persistUser(string $prefix, string $role, bool $active = true): User
    {
        $suffix = bin2hex(random_bytes(5));
        $user = (new User())
            ->setUsername($prefix . '_' . $suffix)
            ->setEmail($prefix . '_' . $suffix . '@example.test')
            ->setRole($role)
            ->setActive($active)
            ->setCreatedAt(new \DateTimeImmutable());
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'TestPassword123!'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $this->userIds[] = (int) $user->getId();

        return $user;
    }

    private function persistRecord(User $owner, string $title): StaffRecord
    {
        $now = new \DateTimeImmutable();
        $record = (new StaffRecord())
            ->setTitle($title)
            ->setDescription('Private record for ownership access testing.')
            ->setStatus('In Progress')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setCreatedBy($owner);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($record);
        $entityManager->flush();
        $this->recordIds[] = (int) $record->getId();

        return $record;
    }
}
