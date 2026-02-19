<?php

namespace App\Repository;

use App\Entity\ChevalierRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChevalierRequest>
 */
class ChevalierRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChevalierRequest::class);
    }

    /**
     * Trouve toutes les demandes en attente (pour l'admin)
     *
     * @return array
     */
    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('cr')
            ->where('cr.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('cr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un email a déjà une demande en attente
     *
     * @param string $email
     * @return bool
     */
    public function hasPendingRequest(string $email): bool
    {
        $count = $this->createQueryBuilder('cr')
            ->select('COUNT(cr.id)')
            ->where('cr.email = :email')
            ->andWhere('cr.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
