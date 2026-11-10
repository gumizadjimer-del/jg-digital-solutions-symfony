<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Message;
use App\Entity\ServiceRequest;
use App\Entity\StaffRecord;
use App\Entity\User;
use App\Service\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExportAndReportingTest extends WebTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $requestIds = [];

    /** @var list<int> */
    private array $recordIds = [];

    /** @var list<int> */
    private array $messageIds = [];

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($this->requestIds as $id) {
                $request = $entityManager->getRepository(ServiceRequest::class)->find($id);
                if ($request instanceof ServiceRequest) {
                    $entityManager->remove($request);
                }
            }
            foreach ($this->recordIds as $id) {
                $record = $entityManager->getRepository(StaffRecord::class)->find($id);
                if ($record instanceof StaffRecord) {
                    $entityManager->remove($record);
                }
            }
            foreach ($this->messageIds as $id) {
                $message = $entityManager->getRepository(Message::class)->find($id);
                if ($message instanceof Message) {
                    $entityManager->remove($message);
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

    public function testAdminExportsFilteredDataAndSeesLiveDashboardReports(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $tag = 'export_' . bin2hex(random_bytes(5));
        $admin = $this->makeUser($entityManager, 'admin', 'admin');
        $staff = $this->makeUser($entityManager, $tag . '_staff', 'staff');
        $customer = $this->makeUser($entityManager, $tag . '_customer', 'user');
        $pending = $this->makeRequest($entityManager, $customer, 'Pending ' . $tag, 'Pending');
        $completed = $this->makeRequest($entityManager, $customer, 'Completed ' . $tag, 'Completed');
        $record = $this->makeRecord($entityManager, $staff, 'Record ' . $tag, 'Completed');
        $message = $this->makeMessage($entityManager, 'Contact ' . $tag, 'Email body ' . $tag);

        $client->loginUser($admin);

        $client->request('GET', '/admin/users/export?' . http_build_query([
            'q' => $tag,
            'role' => 'staff',
            'status' => 'active',
        ]));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('user-accounts-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString($tag . '_staff', $this->downloadBody($client));
        self::assertStringNotContainsString($tag . '_customer', $this->downloadBody($client));

        $client->request('GET', '/admin/records/export?' . http_build_query(['q' => $tag, 'status' => 'Completed']));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('work-records-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString('Record ' . $tag, $this->downloadBody($client));

        $client->request('GET', '/admin/service-requests/export?' . http_build_query([
            'q' => $tag,
            'status' => 'Pending',
        ]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('service-requests-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString('Pending ' . $tag, $this->downloadBody($client));
        self::assertStringNotContainsString('Completed ' . $tag, $this->downloadBody($client));

        $client->request('GET', '/admin/messages/export?' . http_build_query(['q' => $tag]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('contact-messages-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString('Email body ' . $tag, $this->downloadBody($client));

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Requests by status');
        self::assertSelectorTextContains('main', 'Work records by status');
        self::assertSelectorTextContains('main', $tag . '_customer');
        self::assertSelectorTextContains('main', 'Contact ' . $tag);
        self::assertNotNull($pending->getId());
        self::assertNotNull($completed->getId());
        self::assertNotNull($record->getId());
        self::assertNotNull($message->getId());
    }

    public function testStaffAndCustomersCanExportOnlyTheirOwnFilteredRecords(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $tag = 'ownedexport_' . bin2hex(random_bytes(5));
        $staff = $this->makeUser($entityManager, $tag . '_staff', 'staff');
        $otherStaff = $this->makeUser($entityManager, $tag . '_other_staff', 'staff');
        $customer = $this->makeUser($entityManager, $tag . '_customer', 'user');
        $otherCustomer = $this->makeUser($entityManager, $tag . '_other_customer', 'user');
        $this->makeRecord($entityManager, $staff, 'My record ' . $tag, 'In Progress');
        $this->makeRecord($entityManager, $otherStaff, 'Other record ' . $tag, 'In Progress');
        $this->makeRequest($entityManager, $customer, 'My request ' . $tag, 'Pending');
        $this->makeRequest($entityManager, $otherCustomer, 'Other request ' . $tag, 'Pending');

        $client->loginUser($staff);
        $client->request('GET', '/staff/records/export?' . http_build_query(['q' => $tag]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('my-work-records-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString('My record ' . $tag, $this->downloadBody($client));
        self::assertStringNotContainsString('Other record ' . $tag, $this->downloadBody($client));

        $client->loginUser($customer);
        $client->request('GET', '/dashboard/export?' . http_build_query(['q' => $tag]));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('my-service-requests-', $this->downloadHeader($client, 'content-disposition'));
        self::assertStringContainsString('My request ' . $tag, $this->downloadBody($client));
        self::assertStringNotContainsString('Other request ' . $tag, $this->downloadBody($client));

        $client->request('GET', '/admin/users/export');
        self::assertResponseStatusCodeSame(403);
    }

    private function downloadBody(object $client): string
    {
        $response = $client->getInternalResponse();
        self::assertInstanceOf(\Symfony\Component\BrowserKit\Response::class, $response);

        return $response->getContent();
    }

    private function downloadHeader(object $client, string $name): string
    {
        $response = $client->getInternalResponse();
        self::assertInstanceOf(\Symfony\Component\BrowserKit\Response::class, $response);

        return (string) $response->getHeader($name);
    }

    public function testCsvExporterWritesUtf8EscapedAndFormulaSafeRows(): void
    {
        $response = (new CsvExporter())->createResponse(
            'sample.csv',
            ['Description'],
            [['=SUM(A1:A2), "quoted"']],
            static fn (array $row): array => $row
        );

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        self::assertIsString($csv);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString("'=SUM(A1:A2)", $csv);
        self::assertStringContainsString('""quoted""', $csv);
    }

    private function makeUser(EntityManagerInterface $entityManager, string $label, string $role): User
    {
        $suffix = bin2hex(random_bytes(4));
        $user = new User();
        $user->setUsername(substr($label . '_' . $suffix, 0, 50));
        $user->setEmail(substr($label . '_' . $suffix, 0, 35) . '@example.test');
        $user->setPassword(password_hash('ExportTest123!', PASSWORD_DEFAULT));
        $user->setRole($role);
        $user->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($user);
        $entityManager->flush();
        $this->userIds[] = (int) $user->getId();

        return $user;
    }

    private function makeRequest(EntityManagerInterface $entityManager, User $owner, string $description, string $status): ServiceRequest
    {
        $serviceRequest = (new ServiceRequest())
            ->setService('Web Development')
            ->setDescription($description)
            ->setStatus($status)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUser($owner);
        $entityManager->persist($serviceRequest);
        $entityManager->flush();
        $this->requestIds[] = (int) $serviceRequest->getId();

        return $serviceRequest;
    }

    private function makeRecord(EntityManagerInterface $entityManager, User $owner, string $title, string $status): StaffRecord
    {
        $now = new \DateTimeImmutable();
        $record = (new StaffRecord())
            ->setTitle($title)
            ->setDescription('Description ' . $title)
            ->setStatus($status)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setCreatedBy($owner);
        $entityManager->persist($record);
        $entityManager->flush();
        $this->recordIds[] = (int) $record->getId();

        return $record;
    }

    private function makeMessage(EntityManagerInterface $entityManager, string $name, string $body): Message
    {
        $message = (new Message())
            ->setName($name)
            ->setEmail(bin2hex(random_bytes(4)) . '@example.test')
            ->setMessage($body)
            ->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($message);
        $entityManager->flush();
        $this->messageIds[] = (int) $message->getId();

        return $message;
    }
}
