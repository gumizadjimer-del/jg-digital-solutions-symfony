<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['POST'])]
    public function submit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $name = trim($request->request->getString('name'));
        $email = trim($request->request->getString('email'));
        $messageText = trim($request->request->getString('message'));

        if (!$this->isCsrfTokenValid('contact_message', $request->request->getString('_token'))) {
            $this->addFlash('error', 'The contact form expired. Please try again.');
        } elseif (
            $name === '' || mb_strlen($name) > 100 ||
            mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $messageText === '' || mb_strlen($messageText) > 5000
        ) {
            $this->addFlash('error', 'Please provide a valid name, email, and message (up to 5,000 characters).');
        } else {
            $message = new Message();
            $message->setName($name);
            $message->setEmail($email);
            $message->setMessage($messageText);
            $message->setCreatedAt(new \DateTimeImmutable());

            $sender = $this->getUser();
            if ($sender instanceof User) {
                $message->setSubmittedBy($sender);
            }

            $entityManager->persist($message);
            $entityManager->flush();
            $this->addFlash('success', 'Thank you. Your message has been sent.');
        }

        return $this->redirect($this->generateUrl('app_home') . '#contact');
    }
}
