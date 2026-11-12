<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ServiceRequestFlowTest extends WebTestCase
{
    public function testUserSeesOnlyOwnRequestsAndCanCancelPendingRequest(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $ownRequest = $this->makeRequest($owner, 'Private owner request', 'Pending');
        $otherRequest = $this->makeRequest($other, 'Private other request', 'Pending');
        $entityManager->persist($owner);
        $entityManager->persist($other);
        $entityManager->persist($ownRequest);
        $entityManager->persist($otherRequest);
        $entityManager->flush();
        $ownId = $ownRequest->getId();
        $otherId = $otherRequest->getId();
        $ownerId = $owner->getId();
        $otherUserId = $other->getId();

        try {
            $client->loginUser($owner);
            $client->request('GET', '/dashboard');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Private owner request', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Private other request', (string) $client->getResponse()->getContent());

            $client->request('GET', '/service-request/' . $otherId);
            self::assertResponseStatusCodeSame(404);

            $crawler = $client->request('GET', '/service-request/' . $ownId);
            $form = $crawler->selectButton('Cancel request')->form();
            $client->submit($form);
            self::assertResponseRedirects('/service-request/' . $ownId);

            $currentManager = static::getContainer()->get(EntityManagerInterface::class);
            $savedRequest = $currentManager->getRepository(ServiceRequest::class)->find($ownId);
            self::assertInstanceOf(ServiceRequest::class, $savedRequest);
            self::assertSame('Cancelled', $savedRequest->getStatus());
        } finally {
            $this->removeFixtures([$ownId, $otherId], [$ownerId, $otherUserId]);
        }
    }

    public function testDashboardSearchStatusSummaryAndPaginationStayWithinOwner(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->makeUser('filterowner');
        $other = $this->makeUser('filterother');
        $tag = 'dashboard_' . bin2hex(random_bytes(4));
        $requests = [];

        for ($index = 0; $index < 26; ++$index) {
            $requests[] = $this->makeRequest($owner, $tag . ' pending request ' . $index, 'Pending');
        }
        $requests[] = $this->makeRequest($owner, $tag . ' completed request', 'Completed');
        $requests[] = $this->makeRequest($other, $tag . ' other account request', 'Pending');

        $entityManager->persist($owner);
        $entityManager->persist($other);
        foreach ($requests as $serviceRequest) {
            $entityManager->persist($serviceRequest);
        }
        $entityManager->flush();
        $requestIds = array_map(static fn (ServiceRequest $serviceRequest): int => (int) $serviceRequest->getId(), $requests);
        $userIds = [(int) $owner->getId(), (int) $other->getId()];

        try {
            $client->loginUser($owner);
            $client->request('GET', '/dashboard?' . http_build_query([
                'q' => $tag,
                'status' => 'Pending',
                'page' => 2,
            ]));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Showing 26 to 26 of 26 matching requests.');
            self::assertSelectorTextContains('nav.request-pagination', 'Page 2 of 2');
            self::assertStringNotContainsString($tag . ' other account request', (string) $client->getResponse()->getContent());

            $client->request('GET', '/dashboard?' . http_build_query(['q' => $tag, 'status' => 'Completed']));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.request-summary', 'Pending');
            self::assertSelectorTextContains('.request-summary', '26');
            self::assertSelectorTextContains('.request-summary', 'Completed');
            self::assertSelectorTextContains('.request-summary', '1');
            self::assertStringContainsString($tag . ' completed request', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString($tag . ' other account request', (string) $client->getResponse()->getContent());
        } finally {
            $this->removeFixtures($requestIds, $userIds);
        }
    }

    public function testStaffAndAdminsAreRedirectedAwayFromCustomerRequestForm(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $staff = $this->makeUser('staffform', 'staff');
        $admin = $this->makeUser('adminform', 'admin');
        $entityManager->persist($staff);
        $entityManager->persist($admin);
        $entityManager->flush();
        $staffId = (int) $staff->getId();
        $adminId = (int) $admin->getId();

        try {
            $client->loginUser($staff);
            $client->request('GET', '/service-request/new');
            self::assertResponseRedirects('/staff');

            $client->loginUser($admin);
            $client->request('GET', '/service-request/new');
            self::assertResponseRedirects('/admin');
        } finally {
            $this->removeFixtures([], [$staffId, $adminId]);
        }
    }

    private function makeUser(string $label, string $role = 'user'): User
    {
        $suffix = bin2hex(random_bytes(5));
        $user = new User();
        $user->setUsername($label . '_' . $suffix);
        $user->setEmail($label . '_' . $suffix . '@example.test');
        $user->setPassword(password_hash('TestPassword123!', PASSWORD_DEFAULT));
        $user->setRole($role);
        $user->setCreatedAt(new \DateTimeImmutable());

        return $user;
    }

    private function makeRequest(User $user, string $description, string $status): ServiceRequest
    {
        $serviceRequest = new ServiceRequest();
        $serviceRequest->setService('Web Development');
        $serviceRequest->setDescription($description);
        $serviceRequest->setStatus($status);
        $serviceRequest->setCreatedAt(new \DateTimeImmutable());
        $serviceRequest->setUser($user);

        return $serviceRequest;
    }

    private function removeFixtures(array $requestIds, array $userIds): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($requestIds as $id) {
            $request = $entityManager->getRepository(ServiceRequest::class)->find($id);
            if ($request instanceof ServiceRequest) {
                $entityManager->remove($request);
            }
        }
        foreach ($userIds as $id) {
            $user = $entityManager->getRepository(User::class)->find($id);
            if ($user instanceof User) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();
    }
}
