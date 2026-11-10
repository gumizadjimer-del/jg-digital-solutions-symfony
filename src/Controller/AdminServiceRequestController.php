<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/service-requests')]
#[IsGranted('ROLE_ADMIN')]
final class AdminServiceRequestController extends AbstractController
{
    private const PAGE_SIZE = 25;
    private const FILTER_STATUSES = ['Pending', 'In Progress', 'Completed', 'Cancelled', 'Approved', 'Rejected'];

    private const STATUSES = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
    private const STATUS_TRANSITIONS = [
        'Pending' => ['Pending', 'In Progress', 'Cancelled'],
        'In Progress' => ['In Progress', 'Completed', 'Cancelled'],
        'Completed' => ['Completed'],
        'Cancelled' => ['Cancelled'],
        'Approved' => ['Approved', 'In Progress', 'Completed', 'Cancelled'],
        'Rejected' => ['Rejected', 'Cancelled'],
    ];

    #[Route('', name: 'app_admin_service_requests', methods: ['GET'])]
    public function index(Request $request, ServiceRequestRepository $repository): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $status = $request->query->getString('status');
        if (!in_array($status, self::FILTER_STATUSES, true)) {
            $status = '';
        }

        $paginator = new Paginator(
            $repository->buildAdminQueueQuery($search, $status !== '' ? $status : null)->getQuery(),
            false
        );
        $totalRequests = count($paginator);
        $totalPages = max(1, (int) ceil($totalRequests / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return $this->render('admin/service_requests/index.html.twig', [
            'requests' => $paginator,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => self::FILTER_STATUSES,
            'totalRequests' => $totalRequests,
            'page' => $page,
            'totalPages' => $totalPages,
            'firstResult' => $totalRequests === 0 ? 0 : (($page - 1) * self::PAGE_SIZE + 1),
            'lastResult' => min($page * self::PAGE_SIZE, $totalRequests),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_service_request_show', methods: ['GET', 'POST'])]
    public function show(
        ServiceRequest $serviceRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $error = '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'manage_request_' . $serviceRequest->getId(),
                $request->request->getString('_token')
            )) {
                $error = 'Invalid security token.';
            } else {
                $status = $request->request->getString('status');
                $note = trim($request->request->getString('admin_note'));

                $allowedTransitions = self::STATUS_TRANSITIONS[$serviceRequest->getStatus()] ?? [$serviceRequest->getStatus()];
                if (!in_array($status, $allowedTransitions, true)) {
                    $error = 'That status change is not allowed.';
                } elseif (mb_strlen($note) > 5000) {
                    $error = 'Admin notes must be 5,000 characters or fewer.';
                } else {
                    $serviceRequest->setStatus($status);
                    $serviceRequest->setAdminNote($note === '' ? null : $note);
                    $entityManager->flush();
                    $this->addFlash('success', 'Service request updated.');

                    return $this->redirectToRoute('app_admin_service_request_show', [
                        'id' => $serviceRequest->getId(),
                    ]);
                }
            }
        }

        return $this->render('admin/service_requests/show.html.twig', [
            'serviceRequest' => $serviceRequest,
            'statuses' => self::STATUSES,
            'legacyStatus' => !in_array($serviceRequest->getStatus(), self::STATUSES, true),
            'error' => $error,
        ]);
    }
}
