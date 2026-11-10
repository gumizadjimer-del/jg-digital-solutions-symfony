<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_staff_dashboard');
        }

        $user = $this->getUser();

        $serviceRequests = $entityManager
            ->getRepository(ServiceRequest::class)
            ->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC']
            );

        return $this->render('dashboard/index.html.twig', [
            'serviceRequests' => $serviceRequests,
        ]);
    }
}
