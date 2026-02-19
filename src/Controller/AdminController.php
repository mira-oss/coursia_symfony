<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ChevalierRequest;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private EmailService $emailService;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        EmailService $emailService
    ) {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
        $this->emailService = $emailService;
    }

    /**
     * Créer un nouveau Chevalier (après vérification physique)
     * Accessible uniquement par un Admin
     */
    #[Route('/chevaliers/create', name: 'api_admin_create_chevalier', methods: ['POST'])]
    public function createChevalier(Request $request, #[CurrentUser] ?User $admin): JsonResponse
    {
        // Vérification que l'utilisateur connecté est un Admin
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé. Seuls les admins peuvent créer des Chevaliers.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validation des champs requis
        $requiredFields = ['email', 'firstName', 'lastName', 'phone', 'nationality', 'idCardNumber'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Le champ '{$field}' est requis"], 400);
            }
        }

        // Vérifier si l'email n'existe pas déjà
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'Un utilisateur avec cet email existe déjà'], 400);
        }

        // Génération d'un mot de passe temporaire sécurisé
        $temporaryPassword = bin2hex(random_bytes(8)); // 16 caractères hexadécimaux

        // Création du Chevalier
        $chevalier = new User();
        $chevalier->setEmail($data['email']);
        $chevalier->setPassword($this->passwordHasher->hashPassword($chevalier, $temporaryPassword));
        $chevalier->setFirstName($data['firstName']);
        $chevalier->setLastName($data['lastName']);
        $chevalier->setPhone($data['phone']);
        $chevalier->setNationality($data['nationality']);
        $chevalier->setRole('chevalier');
        $chevalier->setBirthDate($data['birthDate'] ?? null);

        // Informations de vérification
        $chevalier->setIsVerified(true);
        $chevalier->setIdCardNumber($data['idCardNumber']);
        $chevalier->setVerifiedAt(new \DateTimeImmutable());
        $chevalier->setVerifiedBy($admin);

        // Si des fichiers ont été uploader (chemins), les enregistrer
        if (!empty($data['idCardPhotoPath'])) {
            $chevalier->setIdCardPhotoPath($data['idCardPhotoPath']);
        }
        if (!empty($data['selfieWithIdPath'])) {
            $chevalier->setSelfieWithIdPath($data['selfieWithIdPath']);
        }

        try {
            $this->em->persist($chevalier);
            $this->em->flush();

            // Envoi de l'email de bienvenue avec les identifiants
            $this->emailService->sendChevalierWelcomeEmail($chevalier, $temporaryPassword);

            return $this->json([
                'message' => 'Chevalier créé avec succès et email envoyé',
                'chevalier' => [
                    'id' => $chevalier->getId(),
                    'email' => $chevalier->getEmail(),
                    'fullName' => $chevalier->getFirstName() . ' ' . $chevalier->getLastName(),
                    'isVerified' => true,
                    'verifiedAt' => $chevalier->getVerifiedAt()->format('Y-m-d H:i:s'),
                ]
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création du Chevalier: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste tous les Chevaliers (pour gestion admin)
     */
    #[Route('/chevaliers', name: 'api_admin_list_chevaliers', methods: ['GET'])]
    public function listChevaliers(#[CurrentUser] ?User $admin): JsonResponse
    {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $chevaliers = $this->em->getRepository(User::class)
            ->findBy(['role' => 'chevalier'], ['createdAt' => 'DESC']);

        return $this->json([
            'chevaliers' => array_map(fn($c) => [
                'id' => $c->getId(),
                'email' => $c->getEmail(),
                'fullName' => $c->getFirstName() . ' ' . $c->getLastName(),
                'phone' => $c->getPhone(),
                'isVerified' => $c->getIsVerified(),
                'verifiedAt' => $c->getVerifiedAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $chevaliers)
        ]);
    }

    /**
     * Liste tous les Élus (pour statistiques admin)
     */
    #[Route('/elus', name: 'api_admin_list_elus', methods: ['GET'])]
    public function listElus(#[CurrentUser] ?User $admin): JsonResponse
    {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $elus = $this->em->getRepository(User::class)
            ->findBy(['role' => 'elu'], ['createdAt' => 'DESC']);

        return $this->json([
            'elus' => array_map(fn($e) => [
                'id' => $e->getId(),
                'email' => $e->getEmail(),
                'fullName' => $e->getFirstName() . ' ' . $e->getLastName(),
                'phone' => $e->getPhone(),
                'createdAt' => $e->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $elus)
        ]);
    }

    /**
     * Désactiver un utilisateur (suspension)
     */
    #[Route('/users/{id}/suspend', name: 'api_admin_suspend_user', methods: ['POST'])]
    public function suspendUser(int $id, #[CurrentUser] ?User $admin): JsonResponse
    {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $user->setIsActive(false);
        $this->em->flush();

        return $this->json(['message' => 'Utilisateur suspendu avec succès']);
    }

    /**
     * Réactiver un utilisateur
     */
    #[Route('/users/{id}/activate', name: 'api_admin_activate_user', methods: ['POST'])]
    public function activateUser(int $id, #[CurrentUser] ?User $admin): JsonResponse
    {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $user->setIsActive(true);
        $this->em->flush();

        return $this->json(['message' => 'Utilisateur réactivé avec succès']);
    }

    // ================= GESTION DES DEMANDES DE CHEVALIER =================

    /**
     * Lister toutes les demandes de Chevalier en attente
     */
    #[Route('/chevalier-requests', name: 'api_admin_chevalier_requests_list', methods: ['GET'])]
    public function listRequests(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user || $user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès réservé aux administrateurs'], 403);
        }

        $pendingRequests = $this->em->getRepository(ChevalierRequest::class)
            ->findPendingRequests();

        return $this->json([
            'requests' => array_map(fn($r) => $this->formatChevalierRequest($r), $pendingRequests)
        ]);
    }

    /**
     * Approuver une demande et créer le compte Chevalier
     */
    #[Route('/chevalier-requests/{id}/approve', name: 'api_admin_chevalier_requests_approve', methods: ['POST'])]
    public function approveRequest(
        int $id,
        Request $request,
        #[CurrentUser] ?User $admin
    ): JsonResponse {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès réservé aux administrateurs'], 403);
        }

        $chevalierRequest = $this->em->getRepository(ChevalierRequest::class)->find($id);

        if (!$chevalierRequest) {
            return $this->json(['error' => 'Demande non trouvée'], 404);
        }

        if ($chevalierRequest->getStatus() !== 'pending') {
            return $this->json(['error' => 'Cette demande a déjà été traitée'], 400);
        }

        $data = json_decode($request->getContent(), true);

        // Vérifier qu'un utilisateur avec cet email n'existe pas déjà
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $chevalierRequest->getEmail()]);

        if ($existingUser) {
            return $this->json(['error' => 'Un compte existe déjà avec cet email'], 400);
        }

        // Générer un mot de passe aléatoire
        $generatedPassword = bin2hex(random_bytes(8));

        // Créer le compte Chevalier
        $chevalier = new User();
        $chevalier->setEmail($chevalierRequest->getEmail());
        $chevalier->setPassword($this->passwordHasher->hashPassword($chevalier, $generatedPassword));
        $chevalier->setFirstName($chevalierRequest->getFirstName());
        $chevalier->setLastName($chevalierRequest->getLastName());
        $chevalier->setPhone($chevalierRequest->getPhone());
        $chevalier->setNationality($chevalierRequest->getNationality());
        $chevalier->setRole('chevalier');
        $chevalier->setIsVerified(true);
        $chevalier->setVerifiedAt(new \DateTimeImmutable());
        $chevalier->setVerifiedBy($admin);

        // Infos de vérification fournies par l'admin
        if (!empty($data['idCardNumber'])) {
            $chevalier->setIdCardNumber($data['idCardNumber']);
        }
        if (!empty($data['idCardPhotoPath'])) {
            $chevalier->setIdCardPhotoPath($data['idCardPhotoPath']);
        }
        if (!empty($data['selfieWithIdPath'])) {
            $chevalier->setSelfieWithIdPath($data['selfieWithIdPath']);
        }

        // Mettre à jour la demande
        $chevalierRequest->setStatus('approved');
        $chevalierRequest->setProcessedBy($admin);
        $chevalierRequest->setProcessedAt(new \DateTimeImmutable());
        $chevalierRequest->setCreatedUser($chevalier);
        $chevalierRequest->setAdminNotes($data['adminNotes'] ?? null);

        try {
            $this->em->persist($chevalier);
            $this->em->persist($chevalierRequest);
            $this->em->flush();

            // Envoyer email avec identifiants
            $this->emailService->sendChevalierWelcomeEmail($chevalier, $generatedPassword);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création du compte: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Compte Chevalier créé avec succès et email envoyé',
            'chevalier' => [
                'id' => $chevalier->getId(),
                'email' => $chevalier->getEmail(),
                'fullName' => $chevalier->getFirstName() . ' ' . $chevalier->getLastName(),
                'isVerified' => $chevalier->getIsVerified(),
            ]
        ], 201);
    }

    /**
     * Rejeter une demande
     */
    #[Route('/chevalier-requests/{id}/reject', name: 'api_admin_chevalier_requests_reject', methods: ['POST'])]
    public function rejectRequest(
        int $id,
        Request $request,
        #[CurrentUser] ?User $admin
    ): JsonResponse {
        if (!$admin || $admin->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès réservé aux administrateurs'], 403);
        }

        $chevalierRequest = $this->em->getRepository(ChevalierRequest::class)->find($id);

        if (!$chevalierRequest) {
            return $this->json(['error' => 'Demande non trouvée'], 404);
        }

        if ($chevalierRequest->getStatus() !== 'pending') {
            return $this->json(['error' => 'Cette demande a déjà été traitée'], 400);
        }

        $data = json_decode($request->getContent(), true);

        $chevalierRequest->setStatus('rejected');
        $chevalierRequest->setProcessedBy($admin);
        $chevalierRequest->setProcessedAt(new \DateTimeImmutable());
        $chevalierRequest->setAdminNotes($data['adminNotes'] ?? 'Demande rejetée');

        try {
            $this->em->persist($chevalierRequest);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors du rejet: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Demande rejetée avec succès',
            'request' => $this->formatChevalierRequest($chevalierRequest)
        ]);
    }

    /**
     * Formater une demande pour la réponse JSON
     */
    private function formatChevalierRequest(ChevalierRequest $request): array
    {
        return [
            'id' => $request->getId(),
            'email' => $request->getEmail(),
            'firstName' => $request->getFirstName(),
            'lastName' => $request->getLastName(),
            'phone' => $request->getPhone(),
            'nationality' => $request->getNationality(),
            'city' => $request->getCity(),
            'message' => $request->getMessage(),
            'status' => $request->getStatus(),
            'adminNotes' => $request->getAdminNotes(),
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            'processedAt' => $request->getProcessedAt()?->format('Y-m-d H:i:s'),
            'processedBy' => $request->getProcessedBy() ? [
                'id' => $request->getProcessedBy()->getId(),
                'name' => $request->getProcessedBy()->getFirstName() . ' ' . $request->getProcessedBy()->getLastName(),
            ] : null,
        ];
    }
}

