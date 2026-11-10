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

    private function makeUser(string $label): User
    {
        $suffix = bin2hex(random_bytes(5));
        $user = new User();
        $user->setUsername($label . '_' . $suffix);
        $user->setEmail($label . '_' . $suffix . '@example.test');
        $user->setPassword(password_hash('TestPassword123!', PASSWORD_DEFAULT));
        $user->setRole('user');
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