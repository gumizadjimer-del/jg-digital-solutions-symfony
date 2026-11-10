<?php

namespace App\Repository;

use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequest>
 */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    public function buildAdminQueueQuery(string $search, ?string $status): QueryBuilder
    {
        $query = $this->createQueryBuilder('serviceRequest')
            ->leftJoin('serviceRequest.user', 'requester')
            ->addSelect('requester')
            ->orderBy('serviceRequest.createdAt', 'DESC')
            ->addOrderBy('serviceRequest.id', 'DESC');

        if ($search !== '') {
            $query
                ->andWhere(
                    'LOWER(serviceRequest.service) LIKE :search OR ' .
                    'LOWER(serviceRequest.description) LIKE :search OR ' .
                    'LOWER(requester.username) LIKE :search OR ' .
                    'LOWER(requester.email) LIKE :search'
                )
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($status !== null) {
            $query->andWhere('serviceRequest.status = :status')->setParameter('status', $status);
        }

        return $query;
    }

    public function buildOwnedListQuery(User $user, string $search, ?string $status): QueryBuilder
    {
        $query = $this->createQueryBuilder('serviceRequest')
            ->andWhere('serviceRequest.user = :owner')
            ->setParameter('owner', $user)
            ->orderBy('serviceRequest.createdAt', 'DESC')
            ->addOrderBy('serviceRequest.id', 'DESC');

        if ($search !== '') {
            $query
                ->andWhere('LOWER(serviceRequest.service) LIKE :search OR LOWER(serviceRequest.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($status !== null) {
            $query->andWhere('serviceRequest.status = :status')->setParameter('status', $status);
        }

        return $query;
    }

    /** @return array<string, int> */
    public function countOwnedByStatus(User $user): array
    {
        $rows = $this->createQueryBuilder('serviceRequest')
            ->select('serviceRequest.status AS status')
            ->addSelect('COUNT(serviceRequest.id) AS requestCount')
            ->andWhere('serviceRequest.user = :owner')
            ->setParameter('owner', $user)
            ->groupBy('serviceRequest.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            'Pending' => 0,
            'In Progress' => 0,
            'Completed' => 0,
            'Cancelled' => 0,
            'Approved' => 0,
            'Rejected' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['requestCount'];
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function countAllByStatus(): array
    {
        $rows = $this->createQueryBuilder('serviceRequest')
            ->select('serviceRequest.status AS status')
            ->addSelect('COUNT(serviceRequest.id) AS requestCount')
            ->groupBy('serviceRequest.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            'Pending' => 0,
            'In Progress' => 0,
            'Completed' => 0,
            'Cancelled' => 0,
            'Approved' => 0,
            'Rejected' => 0,
        ];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['requestCount'];
        }

        return $counts;
    }

    /** @return list<ServiceRequest> */
    public function findRecentForAdmin(int $limit = 5): array
    {
        return $this->buildAdminQueueQuery('', null)
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return ServiceRequest[] Returns an array of ServiceRequest objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ServiceRequest
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
