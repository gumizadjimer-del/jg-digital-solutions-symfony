<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getAuthenticatedUser();
        $errors = [];
        $formData = [
            'username' => (string) $user->getUsername(),
            'email' => (string) $user->getEmail(),
        ];

        if ($request->isMethod('POST')) {
            $formData['username'] = trim($request->request->getString('username'));
            $formData['email'] = strtolower(trim($request->request->getString('email')));

            if (!$this->isCsrfTokenValid('update_profile', $request->request->getString('_token'))) {
                $errors[] = 'The form expired. Please try again.';
            }
            if (mb_strlen($formData['username']) < 3 || mb_strlen($formData['username']) > 50) {
                $errors[] = 'Username must be 3 to 50 characters.';
            }
            if (mb_strlen($formData['email']) > 150 || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
            }

            $existingUsername = $userRepository->findOneBy(['username' => $formData['username']]);
            $existingEmail = $userRepository->findOneBy(['email' => $formData['email']]);
            if ($existingUsername !== null && $existingUsername->getId() !== $user->getId()) {
                $errors[] = 'That username is already in use.';
            }
            if ($existingEmail !== null && $existingEmail->getId() !== $user->getId()) {
                $errors[] = 'That email address is already in use.';
            }

            if ($errors === []) {
                $user->setUsername($formData['username']);
                $user->setEmail($formData['email']);
                $entityManager->flush();
                $this->addFlash('success', 'Your profile has been updated.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }

    #[Route('/password', name: 'app_password_change', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getAuthenticatedUser();
        $errors = [];

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->getString('current_password');
            $newPassword = $request->request->getString('new_password');
            $confirmation = $request->request->getString('confirm_password');

            if (!$this->isCsrfTokenValid('change_password', $request->request->getString('_token'))) {
                $errors[] = 'The form expired. Please try again.';
            }
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $errors[] = 'The current password is incorrect.';
            }
            if (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 4096) {
                $errors[] = 'The new password must be at least 8 characters.';
            }
            if ($newPassword !== $confirmation) {
                $errors[] = 'The password confirmation does not match.';
            }

            if ($errors === []) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();
                $this->addFlash('success', 'Your password has been changed.');

                return $this->redirectToRoute('app_password_change');
            }
        }

        return $this->render('profile/password.html.twig', ['errors' => $errors]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
