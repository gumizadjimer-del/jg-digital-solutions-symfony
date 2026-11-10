<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\CsvExporter;

#[Route('/admin/messages')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMessageController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('/export', name: 'app_admin_messages_export', methods: ['GET'])]
    public function export(Request $request, MessageRepository $repository, CsvExporter $csvExporter): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $rows = $repository->buildAdminListQuery($search)->getQuery()->toIterable();

        return $csvExporter->createResponse(
            'contact-messages-' . date('Y-m-d') . '.csv',
            ['ID', 'Sender name', 'Sender email', 'Linked account', 'Account email', 'Message', 'Received at'],
            $rows,
            static fn (Message $message): array => [
                $message->getId(),
                $message->getName(),
                $message->getEmail(),
                $message->getSubmittedBy()?->getUsername(),
                $message->getSubmittedBy()?->getEmail(),
                $message->getMessage(),
                $message->getCreatedAt()?->format(DATE_ATOM) ?? '',
            ]
        );
    }

    #[Route('', name: 'app_admin_messages', methods: ['GET'])]
    public function index(Request $request, MessageRepository $repository): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $paginator = new Paginator($repository->buildAdminListQuery($search)->getQuery(), false);
        $totalMessages = count($paginator);
        $totalPages = max(1, (int) ceil($totalMessages / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return $this->render('admin/messages/index.html.twig', [
            'messages' => $paginator,
            'search' => $search,
            'totalMessages' => $totalMessages,
            'page' => $page,
            'totalPages' => $totalPages,
            'firstResult' => $totalMessages === 0 ? 0 : (($page - 1) * self::PAGE_SIZE + 1),
            'lastResult' => min($page * self::PAGE_SIZE, $totalMessages),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_message_show', methods: ['GET'])]
    public function show(Message $message, Request $request): Response
    {
        return $this->render('admin/messages/show.html.twig', [
            'message' => $message,
            'search' => mb_substr(trim($request->query->getString('q')), 0, 100),
            'page' => max(1, min(1_000_000, $request->query->getInt('page', 1))),
        ]);
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

        $search = mb_substr(trim($request->request->getString('return_q')), 0, 100);
        $page = max(1, min(1_000_000, $request->request->getInt('return_page', 1)));
        $query = [];
        if ($search !== '') {
            $query['q'] = $search;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $this->redirectToRoute('app_admin_messages', $query);
    }
}
