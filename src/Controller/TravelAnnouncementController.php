<?php

namespace App\Controller;

use App\Entity\TravelAnnouncement;
use App\Entity\User;
use App\Repository\TravelAnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/travels')]
class TravelAnnouncementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TravelAnnouncementRepository $repo
    ) {}

    /**
     * Lister les annonces de voyage approuvées (pour les Élus)
     * GET /api/travels
     */
    #[Route('', name: 'api_travels_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $departureCountry = $request->query->get('from');
        $arrivalCountry   = $request->query->get('to');

        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from(TravelAnnouncement::class, 't')
            ->where('t.status = :status')
            ->andWhere('t.travelDate > :now')
            ->andWhere('t.remainingWeight > 0')
            ->setParameter('status', 'approved')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.travelDate', 'ASC');

        if ($departureCountry) {
            $qb->andWhere('t.departureCountry LIKE :from')
               ->setParameter('from', '%' . $departureCountry . '%');
        }

        if ($arrivalCountry) {
            $qb->andWhere('t.arrivalCountry LIKE :to')
               ->setParameter('to', '%' . $arrivalCountry . '%');
        }

        $travels = $qb->getQuery()->getResult();

        return $this->json([
            'travels' => array_map(fn($t) => $this->format($t), $travels)
        ]);
    }

    /**
     * Chevalier crée une annonce de voyage
     * POST /api/travels
     */
    #[Route('', name: 'api_travels_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($user->getRole() !== 'chevalier') {
            return $this->json(['error' => 'Seuls les Chevaliers peuvent créer des annonces de voyage'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $required = ['departureCity', 'departureCountry', 'arrivalCity', 'arrivalCountry', 'travelDate', 'availableWeight', 'pricePerKg'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Le champ {$field} est obligatoire"], 400);
            }
        }

        // Les documents sont obligatoires (uploadés séparément via /api/uploads)
        if (empty($data['passportPath']) || empty($data['flightTicketPath'])) {
            return $this->json(['error' => 'Le passeport et le billet d\'avion sont obligatoires'], 400);
        }

        try {
            $travelDate = new \DateTimeImmutable($data['travelDate']);
        } catch (\Exception) {
            return $this->json(['error' => 'Format de date invalide'], 400);
        }

        // Vérifier que la date est au moins 24h dans le futur
        $minDate = new \DateTimeImmutable('+24 hours');
        if ($travelDate < $minDate) {
            return $this->json(['error' => 'L\'annonce doit être soumise au moins 24h avant le vol'], 400);
        }

        $travel = new TravelAnnouncement();
        $travel->setChevalier($user)
               ->setDepartureCity($data['departureCity'])
               ->setDepartureCountry($data['departureCountry'])
               ->setArrivalCity($data['arrivalCity'])
               ->setArrivalCountry($data['arrivalCountry'])
               ->setTravelDate($travelDate)
               ->setAvailableWeight((float) $data['availableWeight'])
               ->setRemainingWeight((float) $data['availableWeight'])
               ->setPricePerKg((float) $data['pricePerKg'])
               ->setPassportPath($data['passportPath'])
               ->setFlightTicketPath($data['flightTicketPath'])
               ->setVisaPath($data['visaPath'] ?? null)
               ->setBoardingPassPath($data['boardingPassPath'] ?? null)
               ->setBaggageProofPath($data['baggageProofPath'] ?? null);

        $this->em->persist($travel);
        $this->em->flush();

        return $this->json([
            'message' => 'Annonce soumise avec succès. En attente de vérification par l\'admin.',
            'travel'  => $this->format($travel)
        ], 201);
    }

    /**
     * Mes annonces de voyage (Chevalier)
     * GET /api/travels/mine
     */
    #[Route('/mine', name: 'api_travels_mine', methods: ['GET'])]
    public function mine(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $travels = $this->repo->findBy(['chevalier' => $user], ['createdAt' => 'DESC']);

        return $this->json([
            'travels' => array_map(fn($t) => $this->format($t), $travels)
        ]);
    }

    /**
     * Détail d'une annonce
     * GET /api/travels/{id}
     */
    #[Route('/{id}', name: 'api_travels_show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $travel = $this->repo->find($id);
        if (!$travel) {
            return $this->json(['error' => 'Annonce introuvable'], 404);
        }

        return $this->json(['travel' => $this->format($travel)]);
    }

    private function format(TravelAnnouncement $t): array
    {
        return [
            'id'               => $t->getId(),
            'chevalier'        => [
                'id'   => $t->getChevalier()->getId(),
                'name' => $t->getChevalier()->getFirstName() . ' ' . $t->getChevalier()->getLastName(),
                'phone'=> $t->getChevalier()->getPhone(),
            ],
            'departureCity'    => $t->getDepartureCity(),
            'departureCountry' => $t->getDepartureCountry(),
            'arrivalCity'      => $t->getArrivalCity(),
            'arrivalCountry'   => $t->getArrivalCountry(),
            'travelDate'       => $t->getTravelDate()?->format('Y-m-d H:i'),
            'availableWeight'  => $t->getAvailableWeight(),
            'remainingWeight'  => $t->getRemainingWeight(),
            'pricePerKg'       => $t->getPricePerKg(),
            'status'           => $t->getStatus(),
            'approvedAt'       => $t->getApprovedAt()?->format('Y-m-d H:i:s'),
            'createdAt'        => $t->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
