<?php

namespace App\Entity;

use App\Repository\ChevalierRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * ChevalierRequest représente une demande pour devenir Chevalier
 * Workflow: pending -> approved (compte créé) | rejected
 * Délai de traitement: 72 heures
 *
 * Informations requises:
 * - Identité: NPI (carte CIP), adresse, contact urgence
 * - Véhicule: type (moto/voiture), matricule, documents
 * - Vérification: selfie + selfie avec CIP
 *
 * Informations optionnelles:
 * - Nationalité, marque/modèle/couleur du véhicule
 */
#[ORM\Entity(repositoryClass: ChevalierRequestRepository::class)]
#[ORM\Table(name: 'chevalier_requests')]
class ChevalierRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ================= INFORMATIONS DE BASE =================
    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $nationality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    // ================= INFORMATIONS D'IDENTITE (NPI/CIP) =================
    #[ORM\Column(length: 50)]
    private ?string $npi = null; // Numéro d'Identification Personnel (sur carte CIP)

    #[ORM\Column(type: 'text')]
    private ?string $residenceAddress = null; // Adresse de résidence complète

    #[ORM\Column(length: 100)]
    private ?string $emergencyContactName = null; // Nom du contact d'urgence

    #[ORM\Column(length: 50)]
    private ?string $emergencyContactPhone = null; // Téléphone du contact d'urgence

    // ================= INFORMATIONS VEHICULE =================
    #[ORM\Column(length: 20)]
    private ?string $vehicleType = null; // 'moto' ou 'voiture'

    #[ORM\Column(length: 50)]
    private ?string $vehicleRegistration = null; // Numéro matricule du véhicule

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vehicleDocumentsPath = null; // Papiers du véhicule scannés

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vehicleCardNumber = null; // Numéro carte grise (voiture)

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleBrand = null; // Marque du véhicule

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $vehicleModel = null; // Modèle du véhicule

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vehicleColor = null; // Couleur du véhicule

    // ================= PHOTOS DE VERIFICATION =================
    #[ORM\Column(length: 255)]
    private ?string $selfiePath = null; // Selfie du visage

    #[ORM\Column(length: 255)]
    private ?string $selfieWithCipPath = null; // Selfie avec CIP en main

    // ================= STATUT ET TRAITEMENT =================
    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';
    // pending | approved | rejected

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $processedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdUser = null;

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

    public function setNationality(?string $nationality): self
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

    // ================= GETTERS/SETTERS IDENTITE =================

    public function getNpi(): ?string
    {
        return $this->npi;
    }

    public function setNpi(string $npi): self
    {
        $this->npi = $npi;
        return $this;
    }

    public function getResidenceAddress(): ?string
    {
        return $this->residenceAddress;
    }

    public function setResidenceAddress(string $residenceAddress): self
    {
        $this->residenceAddress = $residenceAddress;
        return $this;
    }

    public function getEmergencyContactName(): ?string
    {
        return $this->emergencyContactName;
    }

    public function setEmergencyContactName(string $emergencyContactName): self
    {
        $this->emergencyContactName = $emergencyContactName;
        return $this;
    }

    public function getEmergencyContactPhone(): ?string
    {
        return $this->emergencyContactPhone;
    }

    public function setEmergencyContactPhone(string $emergencyContactPhone): self
    {
        $this->emergencyContactPhone = $emergencyContactPhone;
        return $this;
    }

    // ================= GETTERS/SETTERS VEHICULE =================

    public function getVehicleType(): ?string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(string $vehicleType): self
    {
        if (!in_array($vehicleType, ['moto', 'voiture'])) {
            throw new \InvalidArgumentException('Type de véhicule invalide (moto ou voiture)');
        }
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getVehicleRegistration(): ?string
    {
        return $this->vehicleRegistration;
    }

    public function setVehicleRegistration(string $vehicleRegistration): self
    {
        $this->vehicleRegistration = $vehicleRegistration;
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

    // ================= GETTERS/SETTERS PHOTOS VERIFICATION =================

    public function getSelfiePath(): ?string
    {
        return $this->selfiePath;
    }

    public function setSelfiePath(string $selfiePath): self
    {
        $this->selfiePath = $selfiePath;
        return $this;
    }

    public function getSelfieWithCipPath(): ?string
    {
        return $this->selfieWithCipPath;
    }

    public function setSelfieWithCipPath(string $selfieWithCipPath): self
    {
        $this->selfieWithCipPath = $selfieWithCipPath;
        return $this;
    }

    // ================= HELPER METHODS =================

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getVehicleDescription(): string
    {
        $desc = ucfirst($this->vehicleType ?? 'N/A');
        if ($this->vehicleBrand) {
            $desc .= ' ' . $this->vehicleBrand;
        }
        if ($this->vehicleModel) {
            $desc .= ' ' . $this->vehicleModel;
        }
        if ($this->vehicleColor) {
            $desc .= ' (' . $this->vehicleColor . ')';
        }
        return $desc;
    }
}
