<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminManagementTest extends WebTestCase
{
    public function testAdminCanUpdateRequestAndManageContactMessages(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $requester = $this->makeUser('requester', 'user');
        $admin = $this->makeUser('admin', 'admin');
        $serviceRequest = new ServiceRequest();
        $serviceRequest->setService('Software Development');
        $serviceRequest->setDescription('Admin status update test');
        $serviceRequest->setStatus('Pending');
        $serviceRequest->setCreatedAt(new \DateTimeImmutable());
        $serviceRequest->setUser($requester);
        $contactMessage = new Message();
        $contactMessage->setName('Test Contact');
        $contactMessage->setEmail('message-' . bin2hex(random_bytes(4)) . '@example.test');
        $contactMessage->setMessage('Admin message listing test');
        $contactMessage->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($requester);
        $entityManager->persist($admin);
        $entityManager->persist($serviceRequest);
        $entityManager->persist($contactMessage);
        $entityManager->flush();
        $requestId = $serviceRequest->getId();
        $requesterId = $requester->getId();
        $adminId = $admin->getId();
        $messageId = $contactMessage->getId();
        $adminPassword = 'AdminTest123!';

        try {
            $crawler = $client->request('GET', '/login');
            $form = $crawler->selectButton('LOGIN')->form();
            $form['_username'] = $admin->getEmail();
            $form['_password'] = $adminPassword;
            $client->submit($form);
            self::assertResponseRedirects('/dashboard');
            $client->followRedirect();
            self::assertResponseRedirects('/admin');
            $client->followRedirect();
            self::assertResponseIsSuccessful();

            $crawler = $client->request('GET', '/admin/service-requests/' . $requestId);
            self::assertResponseIsSuccessful();
            $form = $crawler->selectButton('Save changes')->form();
            $form['status'] = 'In Progress';
            $form['admin_note'] = 'Reviewed by the admin test';
            $client->submit($form);
            self::assertResponseRedirects('/admin/service-requests/' . $requestId);

            $currentManager = static::getContainer()->get(EntityManagerInterface::class);
            $savedRequest = $currentManager->getRepository(ServiceRequest::class)->find($requestId);
            self::assertInstanceOf(ServiceRequest::class, $savedRequest);
            self::assertSame('In Progress', $savedRequest->getStatus());
            self::assertSame('Reviewed by the admin test', $savedRequest->getAdminNote());

            $crawler = $client->request('GET', '/admin/service-requests/' . $requestId);
            $form = $crawler->selectButton('Save changes')->form();
            $form['status'] = 'Pending';
            $form['admin_note'] = 'Reviewed by the admin test';
            $client->submit($form);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.form-error', 'status change is not allowed');
            $currentManager = static::getContainer()->get(EntityManagerInterface::class);
            $savedRequest = $currentManager->getRepository(ServiceRequest::class)->find($requestId);
            self::assertSame('In Progress', $savedRequest?->getStatus());
            $client->request('GET', '/admin/messages');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Admin message listing test', (string) $client->getResponse()->getContent());
            $crawler = $client->request('GET', '/admin/messages/' . $messageId);
            $form = $crawler->selectButton('Delete message')->form();
            $client->submit($form);
            self::assertResponseRedirects('/admin/messages');
        } finally {
            $this->removeFixtures($requestId, $requesterId, $adminId, $messageId);
        }
    }

    public function testRegularUserCannotOpenAdminPages(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->makeUser('regular', 'user');
        $entityManager->persist($user);
        $entityManager->flush();
        $userId = $user->getId();

        try {
            $client->loginUser($user);
            $client->request('GET', '/admin');
            self::assertResponseStatusCodeSame(403);
        } finally {
            $this->removeFixtures(null, null, $userId, null);
        }
    }

    public function testAdminCanSearchAndPageContactMessagesAndKeepListContext(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $this->makeUser('messageadmin', 'admin');
        $entityManager->persist($admin);
        $tag = 'messagepage_' . bin2hex(random_bytes(4));
        $messages = [];

        for ($index = 0; $index < 26; ++$index) {
            $message = new Message();
            $message->setName('Contact ' . $tag . ' ' . $index);
            $message->setEmail($tag . '_' . $index . '@example.test');
            $message->setMessage('Body ' . $tag . ' ' . $index);
            $message->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($message);
            $messages[] = $message;
        }
        $entityManager->flush();
        $adminId = (int) $admin->getId();
        $messageIds = array_map(static fn (Message $message): int => (int) $message->getId(), $messages);

        try {
            $client->loginUser($admin);
            $crawler = $client->request('GET', '/admin/messages?' . http_build_query([
                'q' => $tag,
                'page' => 2,
            ]));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Showing 26 to 26 of 26 matching messages.');
            self::assertSelectorTextContains('nav.pagination', 'Page 2 of 2');
            self::assertStringContainsString('Body ' . $tag . ' 0', (string) $client->getResponse()->getContent());

            $detailUrl = (string) $crawler->filter('.admin-table tbody a')->attr('href');
            self::assertStringContainsString('q=' . $tag, $detailUrl);
            self::assertStringContainsString('page=2', $detailUrl);
            $detailCrawler = $client->request('GET', $detailUrl);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('q=' . $tag, (string) $detailCrawler->filter('.back-button')->attr('href'));
            self::assertStringContainsString('page=2', (string) $detailCrawler->filter('.back-button')->attr('href'));

            $deleteForm = $detailCrawler->selectButton('Delete message')->form();
            $client->submit($deleteForm);
            self::assertResponseRedirects('/admin/messages?q=' . $tag . '&page=2');
        } finally {
            foreach ($messageIds as $id) {
                $message = $entityManager->getRepository(Message::class)->find($id);
                if ($message instanceof Message) {
                    $entityManager->remove($message);
                }
            }
            $savedAdmin = $entityManager->getRepository(User::class)->find($adminId);
            if ($savedAdmin instanceof User) {
                $entityManager->remove($savedAdmin);
            }
            $entityManager->flush();
        }
    }

    private function makeUser(string $label, string $role): User
    {
        $suffix = bin2hex(random_bytes(5));
        $user = new User();
        $user->setUsername($label . '_' . $suffix);
        $user->setEmail($label . '_' . $suffix . '@example.test');
        $user->setPassword(password_hash('AdminTest123!', PASSWORD_DEFAULT));
        $user->setRole($role);
        $user->setCreatedAt(new \DateTimeImmutable());

        return $user;
    }

    private function removeFixtures(?int $requestId, ?int $requesterId, ?int $adminId, ?int $messageId): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        if ($requestId !== null && ($request = $entityManager->getRepository(ServiceRequest::class)->find($requestId))) {
            $entityManager->remove($request);
        }
        if ($messageId !== null && ($message = $entityManager->getRepository(Message::class)->find($messageId))) {
            $entityManager->remove($message);
        }
        foreach ([$requesterId, $adminId] as $id) {
            if ($id !== null && ($user = $entityManager->getRepository(User::class)->find($id))) {
                $entityManager->remove($user);
            }
        }
        $entityManager->flush();
    }
}
