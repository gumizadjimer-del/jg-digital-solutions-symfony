<?php

namespace App\Repository;

use App\Entity\ServiceRequest;
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
