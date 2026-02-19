<?php

namespace App\Controller;

use App\Entity\Journey;
use App\Entity\User;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/journeys')]
class JourneyController extends AbstractController
{
    private EntityManagerInterface $em;
    private CourseService $courseService;

    public function __construct(
        EntityManagerInterface $em,
        CourseService $courseService
    ) {
        $this->em = $em;
        $this->courseService = $courseService;
    }

    /**
     * Créer un nouveau trajet (annonce par un Chevalier)
     */
    #[Route('', name: 'api_journeys_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        // PROTECTION: Seuls les Chevaliers peuvent créer des trajets
        if ($user->getRole() !== 'chevalier') {
            return $this->json(['error' => 'Seuls les Chevaliers peuvent créer des trajets'], 403);
        }

        // PROTECTION: Le Chevalier doit être vérifié
        if (!$user->getIsVerified()) {
            return $this->json([
                'error' => 'Votre compte doit être vérifié par un administrateur avant de pouvoir créer des trajets'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validation
        if (
            empty($data['departureAddress']) || empty($data['deliveryAddress']) ||
            empty($data['departureTime']) || empty($data['pricePerKg'])
        ) {
            return $this->json([
                'error' => 'Champs requis: departureAddress, deliveryAddress, departureTime, pricePerKg'
            ], 400);
        }

        // LOGIQUE MÉTIER : Détermination automatique du type de trajet
        $journeyType = $this->courseService->determineCourseType(
            $data['departureAddress'],
            $data['deliveryAddress']
        );

        $journey = new Journey();
        $journey->setChevalier($user);
        $journey->setDepartureAddress($data['departureAddress']);
        $journey->setDeliveryAddress($data['deliveryAddress']);
        $journey->setType($journeyType);

        try {
            $departureTime = new \DateTimeImmutable($data['departureTime']);
            $journey->setDepartureTime($departureTime);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de date invalide pour departureTime'], 400);
        }

        if (!empty($data['arrivalTime'])) {
            try {
                $arrivalTime = new \DateTimeImmutable($data['arrivalTime']);
                $journey->setArrivalTime($arrivalTime);
            } catch (\Exception $e) {
                // Ignorer si invalide
            }
        }

        $journey->setPricePerKg((float) $data['pricePerKg']);
        $journey->setIsNegotiable($data['isNegotiable'] ?? true);
        $journey->setMaxWeight($data['maxWeight'] ?? null);
        $journey->setNotes($data['notes'] ?? null);

        // Gestion de la capacité de poids (pour national/regional)
        if (!empty($data['maxPackageWeight'])) {
            try {
                $journey->setMaxPackageWeight($data['maxPackageWeight']);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
        }

        // Coordonnées GPS (envoyées par le frontend après sélection sur la carte)
        if (isset($data['departureLatitude'])) {
            $journey->setDepartureLatitude((float) $data['departureLatitude']);
        }
        if (isset($data['departureLongitude'])) {
            $journey->setDepartureLongitude((float) $data['departureLongitude']);
        }
        if (isset($data['deliveryLatitude'])) {
            $journey->setDeliveryLatitude((float) $data['deliveryLatitude']);
        }
        if (isset($data['deliveryLongitude'])) {
            $journey->setDeliveryLongitude((float) $data['deliveryLongitude']);
        }

        try {
            $this->em->persist($journey);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Trajet créé avec succès',
            'journey' => $this->formatJourney($journey)
        ], 201);
    }

    /**
     * Liste les trajets disponibles (pour recherche par Élu)
     */
    #[Route('/available', name: 'api_journeys_available', methods: ['GET'])]
    public function available(Request $request): JsonResponse
    {
        $departure = $request->query->get('departure');
        $delivery = $request->query->get('delivery');
        $deliveryDateStart = $request->query->get('deliveryDateStart');
        $deliveryDateEnd = $request->query->get('deliveryDateEnd');
        $packageWeight = $request->query->get('packageWeight');

        $dateStart = null;
        $dateEnd = null;

        if ($deliveryDateStart) {
            try {
                $dateStart = new \DateTimeImmutable($deliveryDateStart);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }

        if ($deliveryDateEnd) {
            try {
                $dateEnd = new \DateTimeImmutable($deliveryDateEnd);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }

        if ($departure && $delivery) {
            $journeys = $this->em->getRepository(Journey::class)
                ->findMatchingJourneys($departure, $delivery, $dateStart, $dateEnd, $packageWeight);
        } else {
            $journeys = $this->em->getRepository(Journey::class)
                ->findBy(['status' => 'available'], ['departureTime' => 'ASC'], 50);
        }

        return $this->json([
            'journeys' => array_map(fn($j) => $this->formatJourney($j), $journeys)
        ]);
    }

    /**
     * Historique des trajets d'un Chevalier
     */
    #[Route('/my-journeys', name: 'api_journeys_history', methods: ['GET'])]
    public function myJourneys(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $journeys = $this->em->getRepository(Journey::class)
            ->findBy(['chevalier' => $user], ['createdAt' => 'DESC']);

        return $this->json([
            'journeys' => array_map(fn($j) => $this->formatJourney($j), $journeys)
        ]);
    }

    private function formatJourney(Journey $journey): array
    {
        return [
            'id' => $journey->getId(),
            'chevalier' => [
                'id' => $journey->getChevalier()->getId(),
                'name' => $journey->getChevalier()->getFirstName() . ' ' . $journey->getChevalier()->getLastName(),
            ],
            'departureAddress' => $journey->getDepartureAddress(),
            'deliveryAddress' => $journey->getDeliveryAddress(),
            'departureLatitude' => $journey->getDepartureLatitude(),
            'departureLongitude' => $journey->getDepartureLongitude(),
            'deliveryLatitude' => $journey->getDeliveryLatitude(),
            'deliveryLongitude' => $journey->getDeliveryLongitude(),
            'type' => $journey->getType(),
            'departureTime' => $journey->getDepartureTime()->format('Y-m-d H:i:s'),
            'arrivalTime' => $journey->getArrivalTime()?->format('Y-m-d H:i:s'),
            'pricePerKg' => $journey->getPricePerKg(),
            'isNegotiable' => $journey->getIsNegotiable(),
            'maxWeight' => $journey->getMaxWeight(),
            'maxPackageWeight' => $journey->getMaxPackageWeight(),
            'notes' => $journey->getNotes(),
            'status' => $journey->getStatus(),
            'createdAt' => $journey->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
