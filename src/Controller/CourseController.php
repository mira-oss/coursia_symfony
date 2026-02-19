<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Service\CourseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/courses')]
class CourseController extends AbstractController
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
     * Créer une nouvelle course (demande de livraison par un Élu)
     */
    #[Route('', name: 'api_courses_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        // Validation champs obligatoires
        if (empty($data['departureAddress']) || empty($data['deliveryAddress'])) {
            return $this->json(['error' => 'Adresses de départ et de livraison requises'], 400);
        }

        if (empty($data['recipientFirstName']) || empty($data['recipientLastName']) || empty($data['recipientPhone'])) {
            return $this->json(['error' => 'Les informations du destinataire sont obligatoires (nom, prénom, téléphone)'], 400);
        }

        // LOGIQUE MÉTIER : Détermination automatique du type de course
        $courseType = $this->courseService->determineCourseType(
            $data['departureAddress'],
            $data['deliveryAddress']
        );

        $course = new Course();
        $course->setDepartureAddress($data['departureAddress']);
        $course->setDeliveryAddress($data['deliveryAddress']);
        $course->setDescription($data['description'] ?? '');
        $course->setType($courseType); // Auto-détecté
        $course->setTitle('Course de ' . $user->getFirstName());
        $course->setCreatedBy($user);
        $course->setRecipientFirstName($data['recipientFirstName']);
        $course->setRecipientLastName($data['recipientLastName']);
        $course->setRecipientPhone($data['recipientPhone']);

        if (!empty($data['packageType'])) {
            try {
                $course->setPackageType($data['packageType']);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
        }

        // Gestion des dates planifiées (OBLIGATOIRES pour le matching)
        if (empty($data['deliveryDateStart'])) {
            return $this->json(['error' => 'La date de début de livraison est requise'], 400);
        }

        try {
            $deliveryDateStart = new \DateTimeImmutable($data['deliveryDateStart']);
            $course->setDeliveryDateStart($deliveryDateStart);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Format de date invalide pour deliveryDateStart'], 400);
        }

        if (!empty($data['deliveryDateEnd'])) {
            try {
                $deliveryDateEnd = new \DateTimeImmutable($data['deliveryDateEnd']);
                $course->setDeliveryDateEnd($deliveryDateEnd);
            } catch (\Exception $e) {
                // Date invalide, on ignore
            }
        }

        // Gestion du poids du colis (optionnel mais recommandé)
        if (!empty($data['packageWeight'])) {
            try {
                $course->setPackageWeight($data['packageWeight']);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
        }

        // Gestion de la photo du colis (optionnel)
        if (!empty($data['packagePhotoPath'])) {
            $course->setPackagePhotoPath($data['packagePhotoPath']);
        }

        // Coordonnées GPS (envoyées par le frontend après sélection sur la carte)
        if (isset($data['departureLatitude'])) {
            $course->setDepartureLatitude((float) $data['departureLatitude']);
        }
        if (isset($data['departureLongitude'])) {
            $course->setDepartureLongitude((float) $data['departureLongitude']);
        }
        if (isset($data['deliveryLatitude'])) {
            $course->setDeliveryLatitude((float) $data['deliveryLatitude']);
        }
        if (isset($data['deliveryLongitude'])) {
            $course->setDeliveryLongitude((float) $data['deliveryLongitude']);
        }

        try {
            $this->em->persist($course);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Course créée avec succès',
            'course' => [
                'id' => $course->getId(),
                'departureAddress' => $course->getDepartureAddress(),
                'deliveryAddress' => $course->getDeliveryAddress(),
                'departureLatitude' => $course->getDepartureLatitude(),
                'departureLongitude' => $course->getDepartureLongitude(),
                'deliveryLatitude' => $course->getDeliveryLatitude(),
                'deliveryLongitude' => $course->getDeliveryLongitude(),
                'type' => $course->getType(),
                'status' => $course->getStatus(),
                'packageWeight' => $course->getPackageWeight(),
                'packagePhotoPath' => $course->getPackagePhotoPath(),
                'deliveryDateStart' => $course->getDeliveryDateStart()?->format('Y-m-d H:i:s'),
                'deliveryDateEnd' => $course->getDeliveryDateEnd()?->format('Y-m-d H:i:s'),
                'createdAt' => $course->getCreatedAt()->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Lister les courses disponibles (pour les Chevaliers)
     */
    #[Route('/available', name: 'api_courses_available', methods: ['GET'])]
    public function available(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($user->getRole() !== 'chevalier') {
            return $this->json(['error' => 'Seuls les Chevaliers peuvent voir les courses disponibles'], 403);
        }

        // Courses avec statut "created" (pas encore acceptées)
        $courses = $this->em->getRepository(Course::class)
            ->findBy(['status' => 'created'], ['createdAt' => 'DESC'], 50);

        return $this->json([
            'courses' => array_map(fn($c) => $this->formatCourse($c, $user), $courses)
        ]);
    }

    /**
     * Chevalier accepte une course
     */
    #[Route('/{id}/accept', name: 'api_courses_accept', methods: ['POST'])]
    public function accept(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($user->getRole() !== 'chevalier') {
            return $this->json(['error' => 'Seuls les Chevaliers peuvent accepter des courses'], 403);
        }

        if (!$user->getIsVerified()) {
            return $this->json(['error' => 'Votre compte doit être vérifié pour accepter des courses'], 403);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        if ($course->getStatus() !== 'created') {
            return $this->json(['error' => 'Cette course n\'est plus disponible'], 400);
        }

        // Un Chevalier ne peut pas accepter sa propre course
        if ($course->getCreatedBy()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas accepter votre propre course'], 400);
        }

        $course->setStatus('accepted');
        $course->setAcceptedBy($user);

        // Générer le code de récupération (sera affiché à l'Élu)
        $course->generatePickupCode();

        $this->em->flush();

        return $this->json([
            'message' => 'Course acceptée avec succès. Un code de récupération a été envoyé à l\'expéditeur.',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    /**
     * Chevalier refuse/annule une course qu'il a acceptée
     */
    #[Route('/{id}/refuse', name: 'api_courses_refuse', methods: ['POST'])]
    public function refuse(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        // Seul le Chevalier qui a accepté peut refuser
        if (!$course->getAcceptedBy() || $course->getAcceptedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vous n\'êtes pas le Chevalier assigné à cette course'], 403);
        }

        if ($course->getStatus() !== 'accepted') {
            return $this->json(['error' => 'Cette course ne peut pas être refusée (statut: ' . $course->getStatus() . ')'], 400);
        }

        // Remettre la course en disponible
        $course->setStatus('created');
        $course->setAcceptedBy(null);
        $course->setPickupCode(null); // Réinitialiser le code

        $this->em->flush();

        return $this->json([
            'message' => 'Course remise en disponible',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    /**
     * Élu annule sa propre course
     */
    #[Route('/{id}/cancel', name: 'api_courses_cancel', methods: ['POST'])]
    public function cancel(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        // Seul le créateur peut annuler
        if ($course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez annuler que vos propres courses'], 403);
        }

        // On ne peut pas annuler une course déjà en cours ou terminée
        if (in_array($course->getStatus(), ['started', 'finished'])) {
            return $this->json(['error' => 'Impossible d\'annuler une course en cours ou terminée'], 400);
        }

        $course->setStatus('cancelled');
        $course->setAcceptedBy(null);

        $this->em->flush();

        return $this->json([
            'message' => 'Course annulée',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    // ============ SUIVI DE LIVRAISON ============

    /**
     * Chevalier démarre la livraison (en entrant le code de récupération)
     */
    #[Route('/{id}/start', name: 'api_courses_start', methods: ['POST'])]
    public function start(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        // Seul le Chevalier assigné peut démarrer
        if (!$course->getAcceptedBy() || $course->getAcceptedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vous n\'êtes pas le Chevalier assigné à cette course'], 403);
        }

        if ($course->getStatus() !== 'accepted') {
            return $this->json(['error' => 'La course doit être acceptée avant d\'être démarrée'], 400);
        }

        // Vérifier le code de récupération
        $data = json_decode($request->getContent(), true);
        $pickupCode = $data['pickupCode'] ?? null;

        if (!$pickupCode) {
            return $this->json(['error' => 'Code de récupération requis'], 400);
        }

        if ($course->getPickupCode() !== $pickupCode) {
            return $this->json(['error' => 'Code de récupération incorrect'], 400);
        }

        $course->setStatus('started');

        // Générer le code de livraison (sera affiché au destinataire)
        $course->generateDeliveryCode();

        $this->em->flush();

        return $this->json([
            'message' => 'Colis récupéré ! Livraison démarrée. Un code de livraison a été généré.',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    /**
     * Chevalier marque la course comme livrée (en entrant le code de livraison)
     */
    #[Route('/{id}/deliver', name: 'api_courses_deliver', methods: ['POST'])]
    public function deliver(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        // Seul le Chevalier assigné peut marquer comme livré
        if (!$course->getAcceptedBy() || $course->getAcceptedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vous n\'êtes pas le Chevalier assigné à cette course'], 403);
        }

        if ($course->getStatus() !== 'started') {
            return $this->json(['error' => 'La course doit être en cours pour être marquée comme livrée'], 400);
        }

        // Vérifier le code de livraison
        $data = json_decode($request->getContent(), true);
        $deliveryCode = $data['deliveryCode'] ?? null;

        if (!$deliveryCode) {
            return $this->json(['error' => 'Code de livraison requis'], 400);
        }

        if ($course->getDeliveryCode() !== $deliveryCode) {
            return $this->json(['error' => 'Code de livraison incorrect'], 400);
        }

        $course->setStatus('finished'); // Directement terminé car le code prouve la livraison
        $this->em->flush();

        return $this->json([
            'message' => 'Livraison confirmée ! Course terminée avec succès.',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    /**
     * Élu confirme la réception du colis
     */
    #[Route('/{id}/confirm', name: 'api_courses_confirm', methods: ['POST'])]
    public function confirm(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course introuvable'], 404);
        }

        // Seul le créateur (Élu) peut confirmer la réception
        if ($course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Seul le demandeur peut confirmer la réception'], 403);
        }

        if ($course->getStatus() !== 'delivered') {
            return $this->json(['error' => 'Le Chevalier n\'a pas encore marqué cette course comme livrée'], 400);
        }

        $course->setStatus('finished');
        $this->em->flush();

        return $this->json([
            'message' => 'Réception confirmée ! Course terminée avec succès.',
            'course' => $this->formatCourse($course, $user)
        ]);
    }

    /**
     * Obtenir l'historique des courses d'un utilisateur
     */
    #[Route('/history', name: 'api_courses_history', methods: ['GET'])]
    public function history(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        // Récupérer les courses créées par cet utilisateur (Élu)
        $coursesAsElu = $this->em->getRepository(Course::class)
            ->findBy(['createdBy' => $user], ['createdAt' => 'DESC']);

        // Récupérer les courses acceptées par cet utilisateur (Chevalier)
        $coursesAsChevalier = $this->em->getRepository(Course::class)
            ->findBy(['acceptedBy' => $user], ['createdAt' => 'DESC']);

        return $this->json([
            'asElu' => array_map(fn($c) => $this->formatCourse($c, $user), $coursesAsElu),
            'asChevalier' => array_map(fn($c) => $this->formatCourse($c, $user), $coursesAsChevalier),
        ]);
    }

    private function formatCourse(Course $course, ?User $viewer = null): array
    {
        $data = [
            'id' => $course->getId(),
            'title' => $course->getTitle(),
            'description' => $course->getDescription(),
            'departureAddress' => $course->getDepartureAddress(),
            'deliveryAddress' => $course->getDeliveryAddress(),
            'departureLatitude' => $course->getDepartureLatitude(),
            'departureLongitude' => $course->getDepartureLongitude(),
            'deliveryLatitude' => $course->getDeliveryLatitude(),
            'deliveryLongitude' => $course->getDeliveryLongitude(),
            'type' => $course->getType(),
            'status' => $course->getStatus(),
            'packageType' => $course->getPackageType(),
            'packageWeight' => $course->getPackageWeight(),
            'packagePhotoPath' => $course->getPackagePhotoPath(),
            'photos' => $course->getPhotos(),
            'recipientFirstName' => $course->getRecipientFirstName(),
            'recipientLastName' => $course->getRecipientLastName(),
            'recipientPhone' => $course->getRecipientPhone(),
            'deliveryDateStart' => $course->getDeliveryDateStart()?->format('Y-m-d H:i:s'),
            'deliveryDateEnd' => $course->getDeliveryDateEnd()?->format('Y-m-d H:i:s'),
            'createdAt' => $course->getCreatedAt()->format('Y-m-d H:i:s'),
            'createdBy' => [
                'id' => $course->getCreatedBy()->getId(),
                'name' => $course->getCreatedBy()->getFirstName() . ' ' . $course->getCreatedBy()->getLastName(),
                'phone' => $course->getCreatedBy()->getPhone(),
            ],
        ];

        if ($course->getAcceptedBy()) {
            $data['acceptedBy'] = [
                'id' => $course->getAcceptedBy()->getId(),
                'name' => $course->getAcceptedBy()->getFirstName() . ' ' . $course->getAcceptedBy()->getLastName(),
                'phone' => $course->getAcceptedBy()->getPhone(),
            ];
        }

        // Codes de vérification (visibles uniquement pour l'Élu créateur)
        if ($viewer && $course->getCreatedBy()->getId() === $viewer->getId()) {
            // Code de récupération (à donner au Chevalier lors de la récupération)
            if ($course->getPickupCode()) {
                $data['pickupCode'] = $course->getPickupCode();
            }
            // Code de livraison (à transmettre au destinataire)
            if ($course->getDeliveryCode()) {
                $data['deliveryCode'] = $course->getDeliveryCode();
            }
        }

        return $data;
    }
}
