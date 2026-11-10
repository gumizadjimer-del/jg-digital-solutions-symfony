<?php

namespace App\Controller;

use App\Entity\StaffRecord;
use App\Entity\User;
use App\Repository\StaffRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\CsvExporter;

#[Route('/admin/records')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRecordController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('/export', name: 'app_admin_records_export', methods: ['GET'])]
    public function export(Request $request, StaffRecordRepository $recordRepository, CsvExporter $csvExporter): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $status = $request->query->getString('status');
        if (!in_array($status, StaffRecord::STATUSES, true)) {
            $status = '';
        }

        $rows = $recordRepository
            ->buildAdminSearchQuery($search, $status !== '' ? $status : null)
            ->getQuery()
            ->toIterable();

        return $csvExporter->createResponse(
            'work-records-' . date('Y-m-d') . '.csv',
            ['ID', 'Title', 'Description', 'Created by', 'Creator email', 'Status', 'Created at', 'Updated at'],
            $rows,
            static fn (StaffRecord $record): array => [
                $record->getId(),
                $record->getTitle(),
                $record->getDescription(),
                $record->getCreatedBy()?->getUsername(),
                $record->getCreatedBy()?->getEmail(),
                $record->getStatus(),
                $record->getCreatedAt()?->format(DATE_ATOM) ?? '',
                $record->getUpdatedAt()?->format(DATE_ATOM) ?? '',
            ]
        );
    }

    #[Route('', name: 'app_admin_records', methods: ['GET'])]
    public function index(Request $request, StaffRecordRepository $recordRepository): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $status = $request->query->getString('status');
        if (!in_array($status, StaffRecord::STATUSES, true)) {
            $status = '';
        }

        $paginator = new Paginator(
            $recordRepository->buildAdminSearchQuery($search, $status !== '' ? $status : null)->getQuery(),
            false
        );
        $totalRecords = count($paginator);
        $totalPages = max(1, (int) ceil($totalRecords / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return $this->render('admin/records/index.html.twig', [
            'records' => $paginator,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => StaffRecord::STATUSES,
            'totalRecords' => $totalRecords,
            'pageResults' => min(self::PAGE_SIZE, max(0, $totalRecords - (($page - 1) * self::PAGE_SIZE))),
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'app_admin_record_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formData = ['title' => '', 'description' => '', 'status' => StaffRecord::STATUSES[0]];
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->readFormData($request);
            $errors = $this->validateFormData($formData);
            if (!$this->isCsrfTokenValid('admin_record_new', $request->request->getString('_token'))) {
                array_unshift($errors, 'The form expired. Please try again.');
            }

            if ($errors === []) {
                $now = new \DateTimeImmutable();
                $record = (new StaffRecord())
                    ->setTitle($formData['title'])
                    ->setDescription($formData['description'])
                    ->setStatus($formData['status'])
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now)
                    ->setCreatedBy($this->getAdminUser());
                $entityManager->persist($record);
                $entityManager->flush();
                $this->addFlash('success', 'Work record created.');

                return $this->redirectToRoute('app_admin_records');
            }
        }

        return $this->render('admin/records/form.html.twig', [
            'heading' => 'Create Work Record',
            'formAction' => $this->generateUrl('app_admin_record_new'),
            'csrfTokenId' => 'admin_record_new',
            'formData' => $formData,
            'errors' => $errors,
            'statuses' => StaffRecord::STATUSES,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_record_edit', methods: ['GET', 'POST'])]
    public function edit(StaffRecord $record, Request $request, EntityManagerInterface $entityManager): Response
    {
        $tokenId = 'admin_record_edit_' . $record->getId();
        $formData = [
            'title' => (string) $record->getTitle(),
            'description' => (string) $record->getDescription(),
            'status' => $record->getStatus(),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $formData = $this->readFormData($request);
            $errors = $this->validateFormData($formData);
            if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
                array_unshift($errors, 'The form expired. Please try again.');
            }

            if ($errors === []) {
                $record
                    ->setTitle($formData['title'])
                    ->setDescription($formData['description'])
                    ->setStatus($formData['status'])
                    ->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();
                $this->addFlash('success', 'Work record updated.');

                return $this->redirectToRoute('app_admin_records');
            }
        }

        return $this->render('admin/records/form.html.twig', [
            'heading' => 'Edit Work Record',
            'formAction' => $this->generateUrl('app_admin_record_edit', ['id' => $record->getId()]),
            'csrfTokenId' => $tokenId,
            'formData' => $formData,
            'errors' => $errors,
            'statuses' => StaffRecord::STATUSES,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_record_delete', methods: ['POST'])]
    public function delete(StaffRecord $record, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid(
            'delete_admin_record_' . $record->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $entityManager->remove($record);
        $entityManager->flush();
        $this->addFlash('success', 'Work record deleted.');

        return $this->redirectToRoute('app_admin_records');
    }

    /** @return array{title: string, description: string, status: string} */
    private function readFormData(Request $request): array
    {
        return [
            'title' => trim($request->request->getString('title')),
            'description' => trim($request->request->getString('description')),
            'status' => $request->request->getString('status'),
        ];
    }

    /** @param array{title: string, description: string, status: string} $formData
     *  @return list<string>
     */
    private function validateFormData(array $formData): array
    {
        $errors = [];
        if (mb_strlen($formData['title']) < 3 || mb_strlen($formData['title']) > 150) {
            $errors[] = 'Title must be 3 to 150 characters.';
        }
        if ($formData['description'] === '' || mb_strlen($formData['description']) > 5000) {
            $errors[] = 'Description is required and must be 5,000 characters or fewer.';
        }
        if (!in_array($formData['status'], StaffRecord::STATUSES, true)) {
            $errors[] = 'Choose a valid record status.';
        }

        return $errors;
    }

    private function getAdminUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
