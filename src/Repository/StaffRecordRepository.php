<?php

namespace App\Repository;

use App\Entity\StaffRecord;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StaffRecord>
 */
final class StaffRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaffRecord::class);
    }

    public function countOwnedBy(User $user): int
    {
        return $this->count(['createdBy' => $user]);
    }

    /** @return list<StaffRecord> */
    public function findRecentOwnedBy(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('record')
            ->andWhere('record.createdBy = :owner')
            ->setParameter('owner', $user)
            ->orderBy('record.updatedAt', 'DESC')
            ->addOrderBy('record.id', 'DESC')
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function buildAdminSearchQuery(string $term, ?string $status): QueryBuilder
    {
        $query = $this->createQueryBuilder('record')
            ->leftJoin('record.createdBy', 'owner')
            ->addSelect('owner')
            ->orderBy('record.updatedAt', 'DESC')
            ->addOrderBy('record.id', 'DESC');

        if ($term !== '') {
            $query
                ->andWhere('LOWER(record.title) LIKE :term OR LOWER(record.description) LIKE :term OR LOWER(owner.username) LIKE :term OR LOWER(owner.email) LIKE :term')
                ->setParameter('term', '%' . mb_strtolower($term) . '%');
        }

        if ($status !== null && in_array($status, StaffRecord::STATUSES, true)) {
            $query->andWhere('record.status = :status')->setParameter('status', $status);
        }

        return $query;
    }

    public function buildOwnedSearchQuery(User $user, string $term, ?string $status): QueryBuilder
    {
        $query = $this->createQueryBuilder('record')
            ->andWhere('record.createdBy = :owner')
            ->setParameter('owner', $user)
            ->orderBy('record.updatedAt', 'DESC')
            ->addOrderBy('record.id', 'DESC');

        if ($term !== '') {
            $query
                ->andWhere('LOWER(record.title) LIKE :term OR LOWER(record.description) LIKE :term')
                ->setParameter('term', '%' . mb_strtolower($term) . '%');
        }

        if ($status !== null && in_array($status, StaffRecord::STATUSES, true)) {
            $query->andWhere('record.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
