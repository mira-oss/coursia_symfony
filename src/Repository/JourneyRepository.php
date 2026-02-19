<?php

namespace App\Repository;

use App\Entity\Journey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Journey>
 */
class JourneyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Journey::class);
    }

    /**
     * Trouve les trajets disponibles qui correspondent au trajet d'une course
     *
     * @param string $departureAddress Adresse de départ
     * @param string $deliveryAddress Adresse d'arrivée
     * @param \DateTimeImmutable|null $deliveryDateStart Date de début de livraison souhaitée
     * @param \DateTimeImmutable|null $deliveryDateEnd Date de fin de livraison souhaitée
     * @param string|null $packageWeight Poids du colis (leger, moyen, lourd, tres_lourd)
     * @return array
     */
    public function findMatchingJourneys(
        string $departureAddress,
        string $deliveryAddress,
        ?\DateTimeImmutable $deliveryDateStart = null,
        ?\DateTimeImmutable $deliveryDateEnd = null,
        ?string $packageWeight = null
    ): array {
        $qb = $this->createQueryBuilder('j')
            ->where('j.status = :status')
            ->setParameter('status', 'available');

        // Matching géographique
        $qb->andWhere('j.departureAddress LIKE :departure')
            ->setParameter('departure', '%' . $departureAddress . '%');

        $qb->andWhere('j.deliveryAddress LIKE :delivery')
            ->setParameter('delivery', '%' . $deliveryAddress . '%');

        // Matching temporel amélioré
        if ($deliveryDateStart) {
            // Le trajet doit partir après ou pendant la fenêtre de livraison
            $qb->andWhere('j.departureTime >= :deliveryStart')
                ->setParameter('deliveryStart', $deliveryDateStart->modify('-1 day'));
        }

        if ($deliveryDateEnd) {
            // Le trajet doit arriver avant ou pendant la fenêtre de livraison
            $qb->andWhere('j.departureTime <= :deliveryEnd')
                ->setParameter('deliveryEnd', $deliveryDateEnd->modify('+1 day'));
        }

        // Matching de capacité de poids
        if ($packageWeight) {
            $weightHierarchy = [
                'leger' => 1,
                'moyen' => 2,
                'lourd' => 3,
                'tres_lourd' => 4
            ];

            $requestedWeightLevel = $weightHierarchy[$packageWeight] ?? 0;

            // Le Chevalier doit pouvoir transporter au moins ce poids
            // Par exemple, si le colis est "moyen", le Chevalier peut accepter "moyen", "lourd" ou "tres_lourd"
            $acceptableWeights = [];
            foreach ($weightHierarchy as $weight => $level) {
                if ($level >= $requestedWeightLevel) {
                    $acceptableWeights[] = $weight;
                }
            }

            if (!empty($acceptableWeights)) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        'j.maxPackageWeight IS NULL', // Accepte tout
                        $qb->expr()->in('j.maxPackageWeight', $acceptableWeights)
                    )
                );
            }
        }

        $qb->orderBy('j.departureTime', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
