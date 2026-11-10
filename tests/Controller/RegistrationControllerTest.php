<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            static::$kernel->boot();
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $testUsers = $entityManager->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.email LIKE :pattern')
                ->setParameter('pattern', 'test_%@example.test')
                ->getQuery()
                ->getResult();

            foreach ($testUsers as $testUser) {
                $entityManager->remove($testUser);
            }
            if ($testUsers !== []) {
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }
    public function testUserCanRegisterAndLogInWithHashedPassword(): void
    {
        $client = static::createClient();
        $username = 'test_' . bin2hex(random_bytes(5));
        $email = $username . '@example.test';
        $password = 'ExamplePass123!';
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('CREATE ACCOUNT')->form();
        $form['username'] = $username;
        $form['email'] = $email;
        $form['password'] = $password;
        $client->submit($form);
        self::assertResponseRedirects('/login');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        try {
            self::assertSame('user', $user->getRole());
            self::assertNotSame($password, $user->getPassword());
            self::assertTrue(password_verify($password, (string) $user->getPassword()));

            $crawler = $client->followRedirect();
            $form = $crawler->selectButton('LOGIN')->form();
            $form['_username'] = $email;
            $form['_password'] = $password;
            $client->submit($form);
            self::assertResponseRedirects('/dashboard');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h2', 'Welcome');
        } finally {
            $cleanupManager = static::getContainer()->get(EntityManagerInterface::class);
            $registeredUser = $cleanupManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($registeredUser instanceof User) {
                $cleanupManager->remove($registeredUser);
                $cleanupManager->flush();
            }
        }
    }

    public function testInvalidRegistrationIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register', [
            'username' => 'ab',
            'email' => 'not-an-email',
            'password' => 'short',
            '_token' => 'invalid',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'form expired');
    }
}