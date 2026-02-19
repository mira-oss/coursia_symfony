<?php

namespace App\Controller;

use App\Entity\ChevalierRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/chevalier-requests')]
class ChevalierRequestController extends AbstractController
{
    private EntityManagerInterface $em;
    private SluggerInterface $slugger;
    private string $uploadDir;

    public function __construct(EntityManagerInterface $em, SluggerInterface $slugger)
    {
        $this->em = $em;
        $this->slugger = $slugger;
        $this->uploadDir = __DIR__ . '/../../public/uploads/chevalier-requests';
    }

    /**
     * Endpoint PUBLIC pour soumettre une demande de Chevalier
     * Accessible sans authentification
     *
     * Informations requises:
     * - Identité: email, firstName, lastName, phone, nationality, npi, residenceAddress, emergencyContactName, emergencyContactPhone
     * - Véhicule: vehicleType (moto/voiture), vehicleRegistration, vehicleBrand, vehicleModel, vehicleColor
     * - Pour voiture: vehicleCardNumber (carte grise)
     * - Photos: selfiePath, selfieWithCipPath, vehicleDocumentsPath
     */
    #[Route('', name: 'api_chevalier_request_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation des champs requis de base
        $requiredFields = [
            'email', 'firstName', 'lastName', 'phone', 'nationality',
            'npi', 'residenceAddress', 'emergencyContactName', 'emergencyContactPhone',
            'vehicleType', 'vehicleRegistration'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'error' => "Le champ '{$field}' est requis"
                ], 400);
            }
        }

        // Valider le format de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Format d\'email invalide'], 400);
        }

        // Valider le type de véhicule
        if (!in_array($data['vehicleType'], ['moto', 'voiture'])) {
            return $this->json(['error' => 'Type de véhicule invalide (moto ou voiture)'], 400);
        }

        // Si voiture, la carte grise est requise
        if ($data['vehicleType'] === 'voiture' && empty($data['vehicleCardNumber'])) {
            return $this->json(['error' => 'Le numéro de carte grise est requis pour une voiture'], 400);
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

        // Informations de base
        $chevalierRequest->setEmail($data['email']);
        $chevalierRequest->setFirstName($data['firstName']);
        $chevalierRequest->setLastName($data['lastName']);
        $chevalierRequest->setPhone($data['phone']);
        $chevalierRequest->setNationality($data['nationality']);
        $chevalierRequest->setCity($data['city'] ?? null);
        $chevalierRequest->setMessage($data['message'] ?? null);

        // Informations d'identité (NPI/CIP)
        $chevalierRequest->setNpi($data['npi']);
        $chevalierRequest->setResidenceAddress($data['residenceAddress']);
        $chevalierRequest->setEmergencyContactName($data['emergencyContactName']);
        $chevalierRequest->setEmergencyContactPhone($data['emergencyContactPhone']);

        // Informations véhicule
        $chevalierRequest->setVehicleType($data['vehicleType']);
        $chevalierRequest->setVehicleRegistration($data['vehicleRegistration']);
        $chevalierRequest->setVehicleCardNumber($data['vehicleCardNumber'] ?? null);
        $chevalierRequest->setVehicleBrand($data['vehicleBrand'] ?? null);
        $chevalierRequest->setVehicleModel($data['vehicleModel'] ?? null);
        $chevalierRequest->setVehicleColor($data['vehicleColor'] ?? null);

        // Chemins des fichiers (seront mis à jour via l'endpoint upload)
        $chevalierRequest->setSelfiePath($data['selfiePath'] ?? '');
        $chevalierRequest->setSelfieWithCipPath($data['selfieWithCipPath'] ?? '');
        $chevalierRequest->setVehicleDocumentsPath($data['vehicleDocumentsPath'] ?? null);

        try {
            $this->em->persist($chevalierRequest);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la création de la demande: ' . $e->getMessage()
            ], 500);
        }

        return $this->json([
            'message' => 'Demande reçue avec succès. Traitement sous 2h maximum.',
            'requestId' => $chevalierRequest->getId(),
            'status' => $chevalierRequest->getStatus()
        ], 201);
    }

    /**
     * Upload des fichiers pour une demande de chevalier
     * - selfie: Photo du visage
     * - selfieWithCip: Photo avec CIP en main
     * - vehicleDocuments: Papiers du véhicule
     */
    #[Route('/upload', name: 'api_chevalier_request_upload', methods: ['POST'])]
    public function uploadFiles(Request $request): JsonResponse
    {
        // Créer le dossier d'upload s'il n'existe pas
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        $uploadedPaths = [];

        // Types de fichiers acceptés
        $fileTypes = ['selfie', 'selfieWithCip', 'vehicleDocuments'];

        foreach ($fileTypes as $fileType) {
            $file = $request->files->get($fileType);

            if ($file) {
                // Valider le type de fichier
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    return $this->json([
                        'error' => "Type de fichier non autorisé pour {$fileType}. Utilisez JPG, PNG, WEBP ou PDF."
                    ], 400);
                }

                // Valider la taille (max 5MB)
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return $this->json([
                        'error' => "Fichier {$fileType} trop volumineux (max 5MB)"
                    ], 400);
                }

                // Générer un nom de fichier unique
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $extension = $file->guessExtension() ?? 'bin';
                $newFilename = $fileType . '_' . $safeFilename . '_' . uniqid() . '.' . $extension;

                try {
                    $file->move($this->uploadDir, $newFilename);
                    $uploadedPaths[$fileType] = '/uploads/chevalier-requests/' . $newFilename;
                } catch (\Exception $e) {
                    return $this->json([
                        'error' => "Erreur lors de l'upload de {$fileType}: " . $e->getMessage()
                    ], 500);
                }
            }
        }

        if (empty($uploadedPaths)) {
            return $this->json([
                'error' => 'Aucun fichier reçu. Envoyez: selfie, selfieWithCip et/ou vehicleDocuments'
            ], 400);
        }

        return $this->json([
            'message' => 'Fichiers uploadés avec succès',
            'paths' => $uploadedPaths
        ]);
    }

    /**
     * Vérifier le statut d'une demande par email
     */
    #[Route('/status', name: 'api_chevalier_request_status', methods: ['GET'])]
    public function checkStatus(Request $request): JsonResponse
    {
        $email = $request->query->get('email');

        if (!$email) {
            return $this->json(['error' => 'Email requis'], 400);
        }

        $chevalierRequest = $this->em->getRepository(ChevalierRequest::class)
            ->findOneBy(['email' => $email], ['createdAt' => 'DESC']);

        if (!$chevalierRequest) {
            return $this->json(['error' => 'Aucune demande trouvée pour cet email'], 404);
        }

        return $this->json([
            'requestId' => $chevalierRequest->getId(),
            'status' => $chevalierRequest->getStatus(),
            'createdAt' => $chevalierRequest->getCreatedAt()->format('Y-m-d H:i:s'),
            'processedAt' => $chevalierRequest->getProcessedAt()?->format('Y-m-d H:i:s'),
            'adminNotes' => $chevalierRequest->getStatus() === 'rejected' ? $chevalierRequest->getAdminNotes() : null
        ]);
    }
}
