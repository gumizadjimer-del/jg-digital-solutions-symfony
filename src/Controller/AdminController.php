<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Message;
use App\Entity\ServiceRequest;
use App\Entity\StaffRecord;
use App\Repository\MessageRepository;
use App\Repository\ServiceRequestRepository;
use App\Repository\StaffRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        EntityManagerInterface $entityManager,
        MessageRepository $messageRepository,
        ServiceRequestRepository $serviceRequestRepository,
        StaffRecordRepository $staffRecordRepository
    ): Response
    {
        $totalUsers = $entityManager
            ->getRepository(User::class)
            ->count([]);

        $totalStaff = $entityManager
            ->getRepository(User::class)
            ->count(['role' => 'staff']);

        $totalRecords = $entityManager
            ->getRepository(StaffRecord::class)
            ->count([]);

        $totalMessages = $entityManager
            ->getRepository(Message::class)
            ->count([]);

        $totalRequests = $entityManager
            ->getRepository(ServiceRequest::class)
            ->count([]);

        $pendingRequests = $entityManager
            ->getRepository(ServiceRequest::class)
            ->count(['status' => 'Pending']);

        $requestStatusCounts = $serviceRequestRepository->countAllByStatus();
        $recordStatusCounts = $staffRecordRepository->countAllByStatus();

        return $this->render('admin/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalRecords' => $totalRecords,
            'totalMessages' => $totalMessages,
            'totalRequests' => $totalRequests,
            'pendingRequests' => $pendingRequests,
            'requestStatusCounts' => $requestStatusCounts,
            'recordStatusCounts' => $recordStatusCounts,
            'recentRequests' => $serviceRequestRepository->findRecentForAdmin(),
            'recentMessages' => $messageRepository->findRecent(),
        ]);
    }
}
