<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StaffRecord;
use App\Entity\ServiceRequest;
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

    /** @var list<int> */
    private array $serviceRequestIds = [];

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($this->serviceRequestIds as $id) {
                $serviceRequest = $entityManager->getRepository(ServiceRequest::class)->find($id);
                if ($serviceRequest instanceof ServiceRequest) {
                    $entityManager->remove($serviceRequest);
                }
            }
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

    public function testAdminUserListSearchRoleStatusFiltersAndPagination(): void
    {
        $client = static::createClient();
        $admin = $this->persistUser('listadmin', 'admin');
        $client->loginUser($admin);

        $tag = 'userlist_' . bin2hex(random_bytes(4));
        for ($index = 0; $index < 26; ++$index) {
            $this->persistUser($tag . '_staff', 'staff');
        }
        $disabledStaff = $this->persistUser($tag . '_disabled', 'staff', false);
        $regularUser = $this->persistUser($tag . '_regular', 'user');

        $client->request('GET', '/admin/users?' . http_build_query([
            'q' => $tag,
            'role' => 'staff',
            'status' => 'active',
            'page' => 2,
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Showing 26 to 26 of 26 accounts.');
        self::assertSelectorTextContains('nav.pagination', 'Page 2 of 2');
        self::assertSelectorExists('nav.pagination a[href*="page=1"]');

        $client->request('GET', '/admin/users?' . http_build_query([
            'q' => $tag,
            'role' => 'staff',
            'status' => 'disabled',
        ]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $disabledStaff->getUsername(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString((string) $regularUser->getUsername(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/users?' . http_build_query([
            'q' => $tag,
            'role' => 'user',
            'status' => 'active',
        ]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $regularUser->getUsername(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString((string) $disabledStaff->getUsername(), (string) $client->getResponse()->getContent());
    }

    public function testStaffRecordSearchStatusFilterOwnershipAndPagination(): void
    {
        $client = static::createClient();
        $owner = $this->persistUser('recordfilter', 'staff');
        $otherStaff = $this->persistUser('otherfilter', 'staff');
        $client->loginUser($owner);

        $tag = 'staffrecord_' . bin2hex(random_bytes(4));
        for ($index = 0; $index < 26; ++$index) {
            $this->persistRecord($owner, $tag . '_completed_' . $index, 'Completed');
        }
        $this->persistRecord($owner, $tag . '_progress', 'In Progress');
        $foreignRecord = $this->persistRecord($otherStaff, $tag . '_foreign', 'Completed');

        $client->request('GET', '/staff/records?' . http_build_query([
            'q' => $tag,
            'status' => 'Completed',
            'page' => 2,
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Showing 26 to 26 of 26 matching records.');
        self::assertSelectorTextContains('nav.pagination', 'Page 2 of 2');
        self::assertStringNotContainsString((string) $foreignRecord->getTitle(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/staff/records?' . http_build_query(['q' => $tag, 'status' => 'In Progress']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Showing 1 to 1 of 1 matching records.');
        self::assertStringContainsString($tag . '_progress', (string) $client->getResponse()->getContent());
    }

    public function testAdminRecordPaginationIncludesRecordsBeyondOneHundred(): void
    {
        $client = static::createClient();
        $admin = $this->persistUser('recordpageadmin', 'admin');
        $client->loginUser($admin);
        $tag = 'adminrecord_' . bin2hex(random_bytes(4));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $now = new \DateTimeImmutable();
        $records = [];

        for ($index = 0; $index < 101; ++$index) {
            $record = (new StaffRecord())
                ->setTitle($tag . '_' . $index)
                ->setDescription('Created for an isolated pagination test.')
                ->setStatus('Completed')
                ->setCreatedAt($now)
                ->setUpdatedAt($now)
                ->setCreatedBy($admin);
            $entityManager->persist($record);
            $records[] = $record;
        }
        $entityManager->flush();
        foreach ($records as $record) {
            $this->recordIds[] = (int) $record->getId();
        }

        $client->request('GET', '/admin/records?' . http_build_query([
            'q' => $tag,
            'status' => 'Completed',
            'page' => 5,
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Showing 1 of 101 matching records.');
        self::assertSelectorTextContains('nav.pagination', 'Page 5 of 5');
        self::assertStringContainsString($tag . '_0', (string) $client->getResponse()->getContent());
    }

    public function testAdminServiceRequestQueueSearchStatusFilterAndPagination(): void
    {
        $client = static::createClient();
        $admin = $this->persistUser('requestadmin', 'admin');
        $requester = $this->persistUser('requester', 'user');
        $client->loginUser($admin);
        $tag = 'requestqueue_' . bin2hex(random_bytes(4));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $now = new \DateTimeImmutable();
        $requests = [];

        for ($index = 0; $index < 26; ++$index) {
            $serviceRequest = (new ServiceRequest())
                ->setService($tag . '_service_' . $index)
                ->setDescription('Queued request for pagination coverage.')
                ->setStatus('Pending')
                ->setCreatedAt($now)
                ->setUser($requester);
            $entityManager->persist($serviceRequest);
            $requests[] = $serviceRequest;
        }
        $completedRequest = (new ServiceRequest())
            ->setService($tag . '_completed')
            ->setDescription('Completed request excluded by the selected status.')
            ->setStatus('Completed')
            ->setCreatedAt($now)
            ->setUser($requester);
        $entityManager->persist($completedRequest);
        $requests[] = $completedRequest;
        $entityManager->flush();
        foreach ($requests as $request) {
            $this->serviceRequestIds[] = (int) $request->getId();
        }

        $client->request('GET', '/admin/service-requests?' . http_build_query([
            'q' => $tag,
            'status' => 'Pending',
            'page' => 2,
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Showing 26 to 26 of 26 requests.');
        self::assertSelectorTextContains('nav.pagination', 'Page 2 of 2');
        self::assertStringNotContainsString($tag . '_completed', (string) $client->getResponse()->getContent());
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

    private function persistRecord(User $owner, string $title, string $status = 'In Progress'): StaffRecord
    {
        $now = new \DateTimeImmutable();
        $record = (new StaffRecord())
            ->setTitle($title)
            ->setDescription('Private record for ownership access testing.')
            ->setStatus($status)
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
