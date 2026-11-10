<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/service-requests')]
#[IsGranted('ROLE_ADMIN')]
final class AdminServiceRequestController extends AbstractController
{
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
    public function index(ServiceRequestRepository $repository): Response
    {
        return $this->render('admin/service_requests/index.html.twig', [
            'requests' => $repository->findBy([], ['createdAt' => 'DESC']),
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