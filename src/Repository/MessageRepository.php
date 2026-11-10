<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function buildAdminListQuery(string $search): QueryBuilder
    {
        $query = $this->createQueryBuilder('message')
            ->leftJoin('message.submittedBy', 'account')
            ->addSelect('account')
            ->orderBy('message.createdAt', 'DESC')
            ->addOrderBy('message.id', 'DESC');

        if ($search !== '') {
            $query
                ->andWhere('LOWER(message.name) LIKE :search OR LOWER(message.email) LIKE :search OR LOWER(message.message) LIKE :search OR LOWER(account.username) LIKE :search OR LOWER(account.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $query;
    }

    /** @return list<Message> */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('message')
            ->leftJoin('message.submittedBy', 'account')
            ->addSelect('account')
            ->orderBy('message.createdAt', 'DESC')
            ->addOrderBy('message.id', 'DESC')
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Message[] Returns an array of Message objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Message
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
