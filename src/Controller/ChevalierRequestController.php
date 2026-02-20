<?php

namespace App\Controller;

use App\Entity\ChevalierRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/chevalier-requests')]
class ChevalierRequestController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Endpoint PUBLIC pour soumettre une demande de Chevalier
     * Accessible sans authentification
     */
    #[Route('', name: 'api_chevalier_request_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation des champs requis
        if (
            empty($data['email']) || empty($data['firstName']) ||
            empty($data['lastName']) || empty($data['phone']) ||
            empty($data['nationality'])
        ) {
            return $this->json([
                'error' => 'Champs requis: email, firstName, lastName, phone, nationality'
            ], 400);
        }

        // Valider le format de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Format d\'email invalide'], 400);
        }

        // Vérifier si l'email existe déjà comme utilisateur
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return $this->json([
                'error' => 'Un compte existe déjà avec cet email'
            ], 400);
        }

        // Vérifier si une demande en attente existe déjà pour cet email
        $hasPendingRequest = $this->em->getRepository(ChevalierRequest::class)
            ->hasPendingRequest($data['email']);

        if ($hasPendingRequest) {
            return $this->json([
                'error' => 'Une demande est déjà en cours de traitement pour cet email'
            ], 400);
        }

        // Créer la demande
        $chevalierRequest = new ChevalierRequest();
        $chevalierRequest->setEmail($data['email']);
        $chevalierRequest->setFirstName($data['firstName']);
        $chevalierRequest->setLastName($data['lastName']);
        $chevalierRequest->setPhone($data['phone']);
        $chevalierRequest->setNationality($data['nationality']);
        $chevalierRequest->setCity($data['city'] ?? null);
        $chevalierRequest->setMessage($data['message'] ?? null);

        try {
            $this->em->persist($chevalierRequest);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création de la demande: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Demande reçue avec succès. Notre équipe vous contactera sous 48h.',
            'requestId' => $chevalierRequest->getId(),
            'status' => $chevalierRequest->getStatus()
        ], 201);
    }
}
