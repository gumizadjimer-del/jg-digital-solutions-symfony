<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $errors = [];
        $formData = ['username' => '', 'email' => ''];

        if ($request->isMethod('POST')) {
            $formData['username'] = trim($request->request->getString('username'));
            $formData['email'] = strtolower(trim($request->request->getString('email')));
            $password = $request->request->getString('password');

            if (!$this->isCsrfTokenValid(
                'register',
                $request->request->getString('_token')
            )) {
                $errors[] = 'The form expired. Please try again.';
            }

            if (mb_strlen($formData['username']) < 3 || mb_strlen($formData['username']) > 50) {
                $errors[] = 'Username must be between 3 and 50 characters.';
            }

            if (
                mb_strlen($formData['email']) > 150 ||
                !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)
            ) {
                $errors[] = 'Enter a valid email address.';
            }

            if (mb_strlen($password) < 8 || mb_strlen($password) > 4096) {
                $errors[] = 'Password must be at least 8 characters.';
            }

            if ($userRepository->findOneBy(['username' => $formData['username']])) {
                $errors[] = 'That username is already in use.';
            }

            if ($userRepository->findOneBy(['email' => $formData['email']])) {
                $errors[] = 'That email address is already registered.';
            }

            if ($errors === []) {
                $user = new User();
                $user->setUsername($formData['username']);
                $user->setEmail($formData['email']);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setRole('user');
                $user->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Your account has been created. You can now log in.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }
}
