<?php

namespace App\Entity;

use App\Repository\ChevalierRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ChevalierRequest représente une demande pour devenir Chevalier
 * Workflow: pending -> approved (compte créé) | rejected
 */
#[ORM\Entity(repositoryClass: ChevalierRequestRepository::class)]
#[ORM\Table(name: 'chevalier_requests')]
class ChevalierRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 10)]
    private ?string $nationality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null; // Ville actuelle du demandeur

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null; // Motivation du demandeur

    #[ORM\Column(length: 20)]
    private string $requestType = 'national'; // national | international

    // ── Champs communs ──────────────────────────────────────────
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $residenceAddress = null; // Quartier / adresse de résidence

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emergencyContactName = null; // Nom d'un proche

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $emergencyContactPhone = null; // Numéro d'un proche

    // ── National ────────────────────────────────────────────────
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $idCardNumber = null; // Numéro CIP / NPI

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cipPath = null; // PDF CIP / NPI

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $selfiePath = null; // Photo selfie du demandeur

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $selfieWithCipPath = null; // Selfie avec la pièce CIP

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vehicleType = null; // moto | voiture | velo | autres

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleRegistration = null; // Immatriculation du véhicule

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleCardNumber = null; // Numéro de la carte grise

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleBrand = null; // Marque du véhicule

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleModel = null; // Modèle du véhicule

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vehicleColor = null; // Couleur du véhicule

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vehicleDocumentsPath = null; // Documents véhicule (PDF)

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $carteGrisePath = null; // PDF carte grise

    // ── International ────────────────────────────────────────────
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passportNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passportPath = null; // PDF passeport

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $visaPath = null; // PDF visa

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billetAvionPath = null; // PDF billet d'avion

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $destinationCountry = null; // Pays de destination

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destinationAddress = null; // Quartier / hôtel à destination

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';
    // pending | approved | rejected

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null; // Notes de l'admin (raison du rejet, etc.)

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $processedBy = null; // Admin qui a traité la demande

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $requestingUser = null; // Élu connecté qui fait la demande de devenir Chevalier

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdUser = null; // User créé après approbation (si pas de requestingUser)

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending';
    }

    // ================= GETTERS / SETTERS =================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(string $nationality): self
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->status = $status;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?User $processedBy): self
    {
        $this->processedBy = $processedBy;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    public function setRequestType(string $requestType): self
    {
        $this->requestType = $requestType;
        return $this;
    }

    public function getIdCardNumber(): ?string
    {
        return $this->idCardNumber;
    }

    public function setIdCardNumber(?string $idCardNumber): self
    {
        $this->idCardNumber = $idCardNumber;
        return $this;
    }

    public function getCipPath(): ?string
    {
        return $this->cipPath;
    }

    public function setCipPath(?string $cipPath): self
    {
        $this->cipPath = $cipPath;
        return $this;
    }

    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    public function setPassportNumber(?string $passportNumber): self
    {
        $this->passportNumber = $passportNumber;
        return $this;
    }

    public function getPassportPath(): ?string
    {
        return $this->passportPath;
    }

    public function setPassportPath(?string $passportPath): self
    {
        $this->passportPath = $passportPath;
        return $this;
    }

    public function getVisaPath(): ?string
    {
        return $this->visaPath;
    }

    public function setVisaPath(?string $visaPath): self
    {
        $this->visaPath = $visaPath;
        return $this;
    }

    public function getBilletAvionPath(): ?string
    {
        return $this->billetAvionPath;
    }

    public function setBilletAvionPath(?string $billetAvionPath): self
    {
        $this->billetAvionPath = $billetAvionPath;
        return $this;
    }

    public function getDestinationCountry(): ?string
    {
        return $this->destinationCountry;
    }

    public function setDestinationCountry(?string $destinationCountry): self
    {
        $this->destinationCountry = $destinationCountry;
        return $this;
    }

    public function getDestinationAddress(): ?string
    {
        return $this->destinationAddress;
    }

    public function setDestinationAddress(?string $destinationAddress): self
    {
        $this->destinationAddress = $destinationAddress;
        return $this;
    }

    public function getResidenceAddress(): ?string
    {
        return $this->residenceAddress;
    }

    public function setResidenceAddress(?string $residenceAddress): self
    {
        $this->residenceAddress = $residenceAddress;
        return $this;
    }

    public function getEmergencyContactName(): ?string
    {
        return $this->emergencyContactName;
    }

    public function setEmergencyContactName(?string $emergencyContactName): self
    {
        $this->emergencyContactName = $emergencyContactName;
        return $this;
    }

    public function getEmergencyContactPhone(): ?string
    {
        return $this->emergencyContactPhone;
    }

    public function setEmergencyContactPhone(?string $emergencyContactPhone): self
    {
        $this->emergencyContactPhone = $emergencyContactPhone;
        return $this;
    }

    public function getVehicleCardNumber(): ?string
    {
        return $this->vehicleCardNumber;
    }

    public function setVehicleCardNumber(?string $vehicleCardNumber): self
    {
        $this->vehicleCardNumber = $vehicleCardNumber;
        return $this;
    }

    public function getVehicleBrand(): ?string
    {
        return $this->vehicleBrand;
    }

    public function setVehicleBrand(?string $vehicleBrand): self
    {
        $this->vehicleBrand = $vehicleBrand;
        return $this;
    }

    public function getVehicleModel(): ?string
    {
        return $this->vehicleModel;
    }

    public function setVehicleModel(?string $vehicleModel): self
    {
        $this->vehicleModel = $vehicleModel;
        return $this;
    }

    public function getVehicleColor(): ?string
    {
        return $this->vehicleColor;
    }

    public function setVehicleColor(?string $vehicleColor): self
    {
        $this->vehicleColor = $vehicleColor;
        return $this;
    }

    public function getVehicleDocumentsPath(): ?string
    {
        return $this->vehicleDocumentsPath;
    }

    public function setVehicleDocumentsPath(?string $vehicleDocumentsPath): self
    {
        $this->vehicleDocumentsPath = $vehicleDocumentsPath;
        return $this;
    }

    public function getSelfiePath(): ?string
    {
        return $this->selfiePath;
    }

    public function setSelfiePath(?string $selfiePath): self
    {
        $this->selfiePath = $selfiePath;
        return $this;
    }

    public function getSelfieWithCipPath(): ?string
    {
        return $this->selfieWithCipPath;
    }

    public function setSelfieWithCipPath(?string $selfieWithCipPath): self
    {
        $this->selfieWithCipPath = $selfieWithCipPath;
        return $this;
    }

    public function getCarteGrisePath(): ?string
    {
        return $this->carteGrisePath;
    }

    public function setCarteGrisePath(?string $carteGrisePath): self
    {
        $this->carteGrisePath = $carteGrisePath;
        return $this;
    }

    public function getVehicleType(): ?string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?string $vehicleType): self
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getVehicleRegistration(): ?string
    {
        return $this->vehicleRegistration;
    }

    public function setVehicleRegistration(?string $vehicleRegistration): self
    {
        $this->vehicleRegistration = $vehicleRegistration;
        return $this;
    }

    public function getRequestingUser(): ?User
    {
        return $this->requestingUser;
    }

    public function setRequestingUser(?User $requestingUser): self
    {
        $this->requestingUser = $requestingUser;
        return $this;
    }

    public function getCreatedUser(): ?User
    {
        return $this->createdUser;
    }

    public function setCreatedUser(?User $createdUser): self
    {
        $this->createdUser = $createdUser;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
