<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\StaffRecordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class StaffDashboardController extends AbstractController
{
    #[Route('/staff', name: 'app_staff_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(StaffRecordRepository $recordRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('staff/index.html.twig', [
            'staffMember' => $user,
            'recordCount' => $recordRepository->countOwnedBy($user),
            'recentRecords' => $recordRepository->findRecentOwnedBy($user),
        ]);
    }
}
