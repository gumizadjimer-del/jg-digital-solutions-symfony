<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ServiceRequestController extends AbstractController
{
    private const ALLOWED_SERVICES = [
        'Web Development',
        'Software Development',
        'Graphic & Brand Design',
        'Data & Simulation',
    ];

    #[Route('/service-request/new', name: 'app_service_request_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $errors = [];
        $formData = ['service' => '', 'description' => ''];

        if ($request->isMethod('POST')) {
            $formData['service'] = trim($request->request->getString('service'));
            $formData['description'] = trim($request->request->getString('description'));

            if (!$this->isCsrfTokenValid('service_request', $request->request->getString('_token'))) {
                $errors[] = 'The form expired. Please try again.';
            }
            if (!in_array($formData['service'], self::ALLOWED_SERVICES, true)) {
                $errors[] = 'Choose one of the listed services.';
            }
            if ($formData['description'] === '' || mb_strlen($formData['description']) > 5000) {
                $errors[] = 'Description is required and must be no more than 5,000 characters.';
            }

            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            if ($errors === []) {
                $serviceRequest = new ServiceRequest();
                $serviceRequest->setService($formData['service']);
                $serviceRequest->setDescription($formData['description']);
                $serviceRequest->setStatus('Pending');
                $serviceRequest->setCreatedAt(new \DateTimeImmutable());
                $serviceRequest->setUser($user);

                $entityManager->persist($serviceRequest);
                $entityManager->flush();

                $this->addFlash('success', 'Your service request has been submitted.');

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('service_request/index.html.twig', [
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }

    #[Route('/service-request/{id}', name: 'app_service_request_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(ServiceRequest $serviceRequest): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $serviceRequest->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('service_request/show.html.twig', [
            'serviceRequest' => $serviceRequest,
        ]);
    }

    #[Route('/service-request/{id}/cancel', name: 'app_service_request_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(
        ServiceRequest $serviceRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $serviceRequest->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'cancel_request_' . $serviceRequest->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        if ($serviceRequest->getStatus() !== 'Pending') {
            $this->addFlash('error', 'Only pending requests can be cancelled.');

            return $this->redirectToRoute('app_service_request_show', ['id' => $serviceRequest->getId()]);
        }

        $serviceRequest->setStatus('Cancelled');
        $entityManager->flush();
        $this->addFlash('success', 'Your request was cancelled.');

        return $this->redirectToRoute('app_service_request_show', ['id' => $serviceRequest->getId()]);
    }
}
