<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findOneById(Uuid $id): ?Order
    {
        return $this->find($id);
    }

    public function findOneBySourceAndExternalId(string $source, string $externalId): ?Order
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    /**
     * @return list<Order>
     */
    public function findLatest(?OrderStatus $status, int $limit): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        /** @var list<Order> */
        return $qb->getQuery()->getResult();
    }
}
