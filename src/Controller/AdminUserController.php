<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $role = $request->query->getString('role');
        if (!in_array($role, ['user', 'staff', 'admin'], true)) {
            $role = '';
        }
        $status = $request->query->getString('status');
        if (!in_array($status, ['active', 'disabled'], true)) {
            $status = '';
        }

        $paginator = new Paginator($userRepository->buildAdminListQuery($search, $role, $status)->getQuery(), false);
        $totalUsers = count($paginator);
        $totalPages = max(1, (int) ceil($totalUsers / self::PAGE_SIZE));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE);

        return $this->render('admin/users.html.twig', [
            'users' => $paginator,
            'search' => $search,
            'selectedRole' => $role,
            'selectedStatus' => $status,
            'totalUsers' => $totalUsers,
            'page' => $page,
            'totalPages' => $totalPages,
            'firstResult' => $totalUsers === 0 ? 0 : (($page - 1) * self::PAGE_SIZE + 1),
            'lastResult' => min($page * self::PAGE_SIZE, $totalUsers),
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $error = '';
        $formData = ['username' => '', 'email' => '', 'role' => 'staff'];

        if ($request->isMethod('POST')) {
            $formData['username'] = trim($request->request->getString('username'));
            $formData['email'] = strtolower(trim($request->request->getString('email')));
            $formData['role'] = $request->request->getString('role');
            $password = $request->request->getString('password');

            if (!$this->isCsrfTokenValid('create_user', $request->request->getString('_token'))) {
                $error = 'Invalid security token.';
            } elseif (mb_strlen($formData['username']) < 3 || mb_strlen($formData['username']) > 50) {
                $error = 'Username must be 3 to 50 characters.';
            } elseif (mb_strlen($formData['email']) > 150 || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!in_array($formData['role'], ['user', 'staff', 'admin'], true)) {
                $error = 'Choose a valid account role.';
            } elseif (mb_strlen($password) < 8 || mb_strlen($password) > 4096) {
                $error = 'Password must be at least 8 characters.';
            } elseif (
                $userRepository->findOneBy(['username' => $formData['username']]) !== null ||
                $userRepository->findOneBy(['email' => $formData['email']]) !== null
            ) {
                $error = 'That username or email address is already in use.';
            } else {
                $user = new User();
                $user->setUsername($formData['username']);
                $user->setEmail($formData['email']);
                $user->setRole($formData['role']);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setCreatedAt(new \DateTimeImmutable());
                $user->setActive(true);

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Account created successfully.');

                return $this->redirectToRoute('app_admin_users');
            }
        }

        return $this->render('admin/new_user.html.twig', [
            'error' => $error,
            'formData' => $formData,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $error = '';
        $formData = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'is_active' => $user->isActive() ? '1' : '0',
        ];

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid(
                'edit_user_' . $user->getId(),
                $request->request->getString('_token')
            )) {
                $error = 'Invalid security token.';
            } else {
                $username = trim($request->request->getString('username'));
                $email = strtolower(trim($request->request->getString('email')));
                $role = $request->request->getString('role');
                $isActive = $request->request->getString('is_active');
                $newPassword = $request->request->getString('new_password');
                $formData = [
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'is_active' => $isActive,
                ];

                if (
                    mb_strlen($username) < 3 ||
                    mb_strlen($username) > 50
                ) {
                    $error = 'Username must be 3 to 50 characters.';
                } elseif (
                    mb_strlen($email) > 150 ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {
                    $error = 'Please enter a valid email address.';
                } elseif (!in_array($role, ['user', 'staff', 'admin'], true)) {
                    $error = 'Invalid role.';
                } elseif (!in_array($isActive, ['0', '1'], true)) {
                    $error = 'Choose a valid account status.';
                } elseif ($newPassword !== '' && (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 4096)) {
                    $error = 'A reset password must be at least 8 characters.';
                } elseif (
                    $currentUser !== null &&
                    $user->getId() === $currentUser->getId() &&
                    ($role !== 'admin' || $isActive !== '1')
                ) {
                    $error = 'You cannot remove your own admin role or disable your account.';
                } elseif (
                    $user->getRole() === 'admin' &&
                    $user->isActive() &&
                    ($role !== 'admin' || $isActive !== '1') &&
                    $userRepository->countActiveAdmins() <= 1
                ) {
                    $error = 'You cannot disable or demote the last active admin account.';
                } else {
                    $existingUsername = $userRepository->findOneBy([
                        'username' => $username,
                    ]);

                    $existingEmail = $userRepository->findOneBy([
                        'email' => $email,
                    ]);

                    if (
                        ($existingUsername && $existingUsername->getId() !== $user->getId()) ||
                        ($existingEmail && $existingEmail->getId() !== $user->getId())
                    ) {
                        $error = 'Username or email is already being used.';
                    } else {
                        $user->setUsername($username);
                        $user->setEmail($email);
                        $user->setRole($role);
                        $user->setActive($isActive === '1');
                        if ($newPassword !== '') {
                            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                        }

                        $entityManager->flush();

                        $this->addFlash(
                            'success',
                            'User updated successfully.'
                        );

                        return $this->redirectToRoute('app_admin_users');
                    }
                }
            }
        }

        return $this->render('admin/edit_user.html.twig', [
            'user' => $user,
            'error' => $error,
            'formData' => $formData,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$this->isCsrfTokenValid(
            'delete_user_' . $user->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid security token.'
            );
        }

        if ($user->getId() === $currentUser?->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');

            return $this->redirectToRoute('app_admin_users');
        }

        if (
            $user->getRole() === 'admin' &&
            $user->isActive() &&
            $userRepository->countActiveAdmins() <= 1
        ) {
            $this->addFlash('error', 'You cannot delete the last active admin account.');

            return $this->redirectToRoute('app_admin_users');
        }

        // Do not delete users with linked service requests.
        // This protects existing project records.
        if (!$user->getServiceRequests()->isEmpty() || !$user->getStaffRecords()->isEmpty()) {
            $this->addFlash(
                'error',
                'This account owns service requests or work records. Disable it to preserve its history.'
            );

            return $this->redirectToRoute('app_admin_users');
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('app_admin_users');
    }
}
