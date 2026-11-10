<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messages')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMessageController extends AbstractController
{
    #[Route('', name: 'app_admin_messages', methods: ['GET'])]
    public function index(MessageRepository $repository): Response
    {
        return $this->render('admin/messages/index.html.twig', [
            'messages' => $repository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_message_show', methods: ['GET'])]
    public function show(Message $message): Response
    {
        return $this->render('admin/messages/show.html.twig', ['message' => $message]);
    }

    #[Route('/{id}/delete', name: 'app_admin_message_delete', methods: ['POST'])]
    public function delete(Message $message, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid(
            'delete_message_' . $message->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $entityManager->remove($message);
        $entityManager->flush();
        $this->addFlash('success', 'Message deleted.');

        return $this->redirectToRoute('app_admin_messages');
    }
}