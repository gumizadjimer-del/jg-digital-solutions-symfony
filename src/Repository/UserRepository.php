<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function buildAdminListQuery(string $search, string $role, string $status): QueryBuilder
    {
        $query = $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC')
            ->addOrderBy('user.id', 'DESC');

        if ($search !== '') {
            $query
                ->andWhere('LOWER(user.username) LIKE :search OR LOWER(user.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if (in_array($role, ['user', 'staff', 'admin'], true)) {
            $query->andWhere('user.role = :role')->setParameter('role', $role);
        }

        if ($status === 'active' || $status === 'disabled') {
            $query
                ->andWhere('user.isActive = :active')
                ->setParameter('active', $status === 'active');
        }

        return $query;
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.role = :role')
            ->andWhere('user.isActive = :active')
            ->setParameter('role', 'admin')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
