<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ServiceRequest;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\CsvExporter;

final class DashboardController extends AbstractController
{
    private const PAGE_SIZE = 25;
    private const FILTER_STATUSES = ['Pending', 'In Progress', 'Completed', 'Cancelled', 'Approved', 'Rejected'];

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, ServiceRequestRepository $repository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $status = $request->query->getString('status');
        if (!in_array($status, self::FILTER_STATUSES, true)) {
            $status = '';
        }

        $paginator = new Paginator(
            $repository->buildOwnedListQuery($user, $search, $status !== '' ? $status : null)->getQuery(),
            false
        );
        $totalRequests = count($paginator);
        $totalPages = max(1, (int) ceil($totalRequests / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        $requestStatusCounts = $repository->countOwnedByStatus($user);

        return $this->render('dashboard/index.html.twig', [
            'serviceRequests' => $paginator,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => self::FILTER_STATUSES,
            'requestStatusCounts' => $requestStatusCounts,
            'allRequestCount' => array_sum($requestStatusCounts),
            'totalRequests' => $totalRequests,
            'page' => $page,
            'totalPages' => $totalPages,
            'firstResult' => $totalRequests === 0 ? 0 : (($page - 1) * self::PAGE_SIZE + 1),
            'lastResult' => min($page * self::PAGE_SIZE, $totalRequests),
        ]);
    }

    #[Route('/dashboard/export', name: 'app_dashboard_export', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function export(Request $request, ServiceRequestRepository $repository, CsvExporter $csvExporter): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $status = $request->query->getString('status');
        if (!in_array($status, self::FILTER_STATUSES, true)) {
            $status = '';
        }

        $rows = $repository
            ->buildOwnedListQuery($user, $search, $status !== '' ? $status : null)
            ->getQuery()
            ->toIterable();

        return $csvExporter->createResponse(
            'my-service-requests-' . date('Y-m-d') . '.csv',
            ['ID', 'Service', 'Description', 'Status', 'Admin note', 'Submitted at'],
            $rows,
            static fn (ServiceRequest $serviceRequest): array => [
                $serviceRequest->getId(),
                $serviceRequest->getService(),
                $serviceRequest->getDescription(),
                $serviceRequest->getStatus(),
                $serviceRequest->getAdminNote(),
                $serviceRequest->getCreatedAt()?->format(DATE_ATOM) ?? '',
            ]
        );
    }
}
