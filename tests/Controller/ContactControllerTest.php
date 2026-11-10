<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactControllerTest extends WebTestCase
{
    public function testVisitorCanSubmitContactMessage(): void
    {
        $client = static::createClient();
        $email = 'contact-' . bin2hex(random_bytes(5)) . '@example.test';
        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('SEND MESSAGE')->form();
        $form['name'] = 'Test Visitor';
        $form['email'] = $email;
        $form['message'] = 'Automated contact flow check';
        $client->submit($form);
        self::assertResponseRedirects('/#contact');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $message = $entityManager->getRepository(Message::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Message::class, $message);
        self::assertNull($message->getSubmittedBy());

        $entityManager->remove($message);
        $entityManager->flush();
    }

    public function testLoggedInContactMessageLinksToSubmittingAccount(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $user = new User();
        $user->setUsername('contact_' . $suffix);
        $user->setEmail('contact_' . $suffix . '@example.test');
        $user->setPassword(password_hash('ContactTest123!', PASSWORD_DEFAULT));
        $user->setRole('user');
        $user->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($user);
        $entityManager->flush();

        $message = null;
        try {
            $client->loginUser($user);
            $crawler = $client->request('GET', '/');
            $form = $crawler->selectButton('SEND MESSAGE')->form();
            $form['name'] = $user->getUsername();
            $form['email'] = $user->getEmail();
            $form['message'] = 'Authenticated contact flow check';
            $client->submit($form);
            self::assertResponseRedirects('/#contact');

            $entityManager->clear();
            $message = $entityManager->getRepository(Message::class)->findOneBy(['email' => $user->getEmail()]);
            self::assertInstanceOf(Message::class, $message);
            self::assertSame($user->getId(), $message->getSubmittedBy()?->getId());
        } finally {
            if ($message instanceof Message) {
                $entityManager->remove($message);
            }
            $savedUser = $entityManager->getRepository(User::class)->find($user->getId());
            if ($savedUser instanceof User) {
                $entityManager->remove($savedUser);
            }
            $entityManager->flush();
        }
    }
}
