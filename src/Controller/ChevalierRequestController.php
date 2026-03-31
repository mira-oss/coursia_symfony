<?php

namespace App\Controller;

use App\Entity\ChevalierRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/api/chevalier-requests')]
class ChevalierRequestController extends AbstractController
{
    private EntityManagerInterface $em;
    private string $uploadsDir;

    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $this->em = $em;
        $this->uploadsDir = $params->get('kernel.project_dir') . '/public/uploads/chevalier_docs';
    }

    private function uploadPdf($file, string $prefix, int $userId): ?string
    {
        if ($file === null) return null;
        $mime = $file->getMimeType();
        $allowedMimes = ['application/pdf', 'application/octet-stream', 'application/x-pdf', 'binary/octet-stream'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($mime, $allowedMimes) && $ext !== 'pdf') {
            throw new \InvalidArgumentException('Le fichier ' . $prefix . ' doit être un PDF');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Le fichier ' . $prefix . ' ne doit pas dépasser 5 Mo');
        }
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0775, true);
        }
        $fileName = uniqid($prefix . '_') . '_' . $userId . '.pdf';
        $file->move($this->uploadsDir, $fileName);
        return 'uploads/chevalier_docs/' . $fileName;
    }

    /**
     * Soumettre une demande de Chevalier.
     * - Si connecté en tant qu'Élu : mise à niveau du compte existant
     * - Si non connecté : demande avec création de compte à l'approbation
     */
    #[Route('', name: 'api_chevalier_request_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $currentUser): JsonResponse
    {
        // Les données arrivent en multipart/form-data (pour supporter l'upload de fichier)
        $data = $request->request->all();

        if ($currentUser === null) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($currentUser->getRole() !== 'elu') {
            return $this->json(['error' => 'Seul un Élu peut faire une demande pour devenir Chevalier'], 400);
        }

        $hasPendingRequest = $this->em->getRepository(ChevalierRequest::class)
            ->hasPendingRequest($currentUser->getEmail());

        if ($hasPendingRequest) {
            return $this->json(['error' => 'Vous avez déjà une demande en cours de traitement'], 400);
        }

        $requestType = $data['requestType'] ?? 'national';
        if (!in_array($requestType, ['national', 'international'])) {
            return $this->json(['error' => 'Type de demande invalide (national ou international)'], 400);
        }

        try {
            $uid = $currentUser->getId();
            if ($requestType === 'national') {
                $cipPath        = $this->uploadPdf($request->files->get('cip'), 'cip', $uid);
                $carteGrisePath = $this->uploadPdf($request->files->get('carteGrise'), 'cg', $uid);
                $passportPath = $visaPath = $billetAvionPath = null;
            } else {
                $passportPath   = $this->uploadPdf($request->files->get('passport'), 'passport', $uid);
                $visaPath       = $this->uploadPdf($request->files->get('visa'), 'visa', $uid);
                $billetAvionPath = $this->uploadPdf($request->files->get('billetAvion'), 'billet', $uid);
                $cipPath = $carteGrisePath = null;
            }
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $chevalierRequest = new ChevalierRequest();
        $chevalierRequest->setEmail($currentUser->getEmail());
        $chevalierRequest->setFirstName($currentUser->getFirstName());
        $chevalierRequest->setLastName($currentUser->getLastName());
        $chevalierRequest->setPhone($currentUser->getPhone() ?? '');
        $chevalierRequest->setNationality($currentUser->getNationality() ?? '');
        $chevalierRequest->setRequestType($requestType);
        $chevalierRequest->setResidenceAddress($data['residenceAddress'] ?? null);
        $chevalierRequest->setEmergencyContactPhone($data['emergencyContactPhone'] ?? null);
        // National
        $chevalierRequest->setIdCardNumber($data['idCardNumber'] ?? null);
        $chevalierRequest->setCipPath($cipPath);
        $chevalierRequest->setVehicleType($data['vehicleType'] ?? null);
        $chevalierRequest->setVehicleRegistration($data['vehicleRegistration'] ?? null);
        $chevalierRequest->setCarteGrisePath($carteGrisePath);
        // International
        $chevalierRequest->setPassportNumber($data['passportNumber'] ?? null);
        $chevalierRequest->setPassportPath($passportPath);
        $chevalierRequest->setVisaPath($visaPath);
        $chevalierRequest->setBilletAvionPath($billetAvionPath);
        $chevalierRequest->setDestinationCountry($data['destinationCountry'] ?? null);
        $chevalierRequest->setDestinationAddress($data['destinationAddress'] ?? null);
        $chevalierRequest->setMessage($data['message'] ?? null);
        $chevalierRequest->setRequestingUser($currentUser);

        try {
            $this->em->persist($chevalierRequest);
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création de la demande: ' . $e->getMessage()], 500);
        }

        return $this->json([
            'message' => 'Demande envoyée avec succès. Notre équipe l\'examinera sous 48h.',
            'requestId' => $chevalierRequest->getId(),
            'status' => $chevalierRequest->getStatus()
        ], 201);
    }

    /**
     * Vérifier le statut de la demande de l'utilisateur connecté
     */
    #[Route('/my-status', name: 'api_chevalier_request_my_status', methods: ['GET'])]
    public function myStatus(#[CurrentUser] ?User $currentUser): JsonResponse
    {
        if (!$currentUser) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $request = $this->em->getRepository(ChevalierRequest::class)
            ->findOneBy(['email' => $currentUser->getEmail()], ['createdAt' => 'DESC']);

        if (!$request) {
            return $this->json(['hasRequest' => false]);
        }

        return $this->json([
            'hasRequest' => true,
            'status' => $request->getStatus(),
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            'adminNotes' => $request->getAdminNotes(),
        ]);
    }
}
