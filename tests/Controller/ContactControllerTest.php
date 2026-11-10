<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
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

        $entityManager->remove($message);
        $entityManager->flush();
    }
}