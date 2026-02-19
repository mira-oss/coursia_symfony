<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseOffer;
use App\Entity\User;
use App\Repository\CourseOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/courses')]
class CourseOfferController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CourseOfferRepository $repo
    ) {}

    /**
     * Chevalier soumet une offre sur une course nationale
     * POST /api/courses/{id}/offers
     */
    #[Route('/{id}/offers', name: 'api_offers_create', methods: ['POST'])]
    public function create(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($user->getRole() !== 'chevalier') {
            return $this->json(['error' => 'Seuls les Chevaliers peuvent faire des offres'], 403);
        }

        $course = $this->em->getRepository(Course::class)->find($id);
        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        if ($course->getType() !== 'national') {
            return $this->json(['error' => 'Les offres ne sont disponibles que pour les courses nationales'], 400);
        }

        if ($course->getStatus() !== 'created') {
            return $this->json(['error' => 'Cette course n\'accepte plus d\'offres'], 400);
        }

        if ($course->getCreatedBy()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas faire une offre sur votre propre course'], 400);
        }

        // Vérifier si ce Chevalier a déjà une offre pending sur cette course
        $existing = $this->repo->findOneBy(['course' => $course, 'chevalier' => $user, 'status' => 'pending']);
        if ($existing) {
            return $this->json(['error' => 'Vous avez déjà une offre en attente sur cette course'], 400);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['price']) || !is_numeric($data['price'])) {
            return $this->json(['error' => 'Le prix est obligatoire'], 400);
        }

        $offer = new CourseOffer();
        $offer->setCourse($course)
              ->setChevalier($user)
              ->setPrice((float) $data['price'])
              ->setIsNegotiable((bool) ($data['isNegotiable'] ?? false))
              ->setMessage($data['message'] ?? null);

        $this->em->persist($offer);
        $this->em->flush();

        return $this->json([
            'message' => 'Offre soumise avec succès',
            'offer'   => $this->format($offer)
        ], 201);
    }

    /**
     * Élu voit les offres reçues sur sa course
     * GET /api/courses/{id}/offers
     */
    #[Route('/{id}/offers', name: 'api_offers_list', methods: ['GET'])]
    public function list(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);
        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        if ($course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vous n\'êtes pas le propriétaire de cette course'], 403);
        }

        $offers = $this->repo->findBy(['course' => $course], ['createdAt' => 'DESC']);

        return $this->json([
            'offers' => array_map(fn($o) => $this->format($o), $offers)
        ]);
    }

    /**
     * Élu accepte une offre → course acceptée, autres offres rejetées
     * PATCH /api/courses/{courseId}/offers/{offerId}/accept
     */
    #[Route('/{courseId}/offers/{offerId}/accept', name: 'api_offers_accept', methods: ['PATCH'])]
    public function accept(int $courseId, int $offerId, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Course introuvable ou non autorisé'], 404);
        }

        $offer = $this->repo->find($offerId);
        if (!$offer || $offer->getCourse()->getId() !== $courseId) {
            return $this->json(['error' => 'Offre introuvable'], 404);
        }

        if ($offer->getStatus() !== 'pending') {
            return $this->json(['error' => 'Cette offre n\'est plus en attente'], 400);
        }

        // Accepter l'offre choisie
        $offer->setStatus('accepted');

        // Mettre à jour la course
        $course->setStatus('accepted');
        $course->setAcceptedBy($offer->getChevalier());
        $course->generatePickupCode();

        // Rejeter toutes les autres offres pending
        $otherOffers = $this->repo->findBy(['course' => $course, 'status' => 'pending']);
        foreach ($otherOffers as $other) {
            if ($other->getId() !== $offer->getId()) {
                $other->setStatus('rejected');
            }
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Offre acceptée. Le Chevalier va récupérer votre colis.',
            'offer'   => $this->format($offer)
        ]);
    }

    /**
     * Élu rejette une offre
     * PATCH /api/courses/{courseId}/offers/{offerId}/reject
     */
    #[Route('/{courseId}/offers/{offerId}/reject', name: 'api_offers_reject', methods: ['PATCH'])]
    public function reject(int $courseId, int $offerId, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $offer = $this->repo->find($offerId);
        if (!$offer || $offer->getCourse()->getId() !== $courseId) {
            return $this->json(['error' => 'Offre introuvable'], 404);
        }

        $offer->setStatus('rejected');
        $this->em->flush();

        return $this->json(['message' => 'Offre rejetée']);
    }

    private function format(CourseOffer $offer): array
    {
        return [
            'id'           => $offer->getId(),
            'courseId'     => $offer->getCourse()->getId(),
            'chevalier'    => [
                'id'   => $offer->getChevalier()->getId(),
                'name' => $offer->getChevalier()->getFirstName() . ' ' . $offer->getChevalier()->getLastName(),
                'phone'=> $offer->getChevalier()->getPhone(),
            ],
            'price'        => $offer->getPrice(),
            'isNegotiable' => $offer->getIsNegotiable(),
            'message'      => $offer->getMessage(),
            'status'       => $offer->getStatus(),
            'createdAt'    => $offer->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
