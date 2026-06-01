<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    // Find by public UUID (not internal integer ID)
    public function findByUuid(string $uuid): ?Account
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

     // Find with PESSIMISTIC WRITE lock — used inside transfer transaction
    // Generates: SELECT * FROM accounts WHERE uuid = ? FOR UPDATE
    public function findByUuidForUpdate(string $uuid): ?Account
    {
        return $this->createQueryBuilder('a')
            ->where('a.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Account[] Returns an array of Account objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Account
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
