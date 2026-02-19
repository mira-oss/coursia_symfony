<?php

namespace App\Entity;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 25)]
    private ?string $firstName = null;

    #[ORM\Column(length: 25)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    private ?string $nationality = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 50)]
    private ?string $role = null; // elu | chevalier | admin

    #[ORM\Column(type: "string", length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: "string", length: 25, nullable: true)]
    private ?string $birthDate = null;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    // ===== CHAMPS DE VÉRIFICATION (pour Chevaliers) =====
    #[ORM\Column(type: "boolean")]
    private bool $isVerified = false;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $idCardNumber = null; // NPI (Numero d'Identification Personnel)

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $residenceAddress = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $emergencyContactName = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $emergencyContactPhone = null;

    // ===== CHAMPS VEHICULE (pour Chevaliers) =====
    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $vehicleType = null; // 'moto' ou 'voiture'

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $vehicleRegistration = null; // Numero matricule

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $vehicleCardNumber = null; // Carte grise (voiture)

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $vehicleBrand = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $vehicleModel = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $vehicleColor = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $vehicleDocumentsPath = null;

    // ===== PHOTOS DE VERIFICATION =====
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $idCardPhotoPath = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $selfiePath = null; // Selfie du visage

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $selfieWithIdPath = null; // Selfie avec CIP en main

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $avatar = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->isVerified = false;
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

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(string $nationality): static
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function setBirthDate(?string $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ================= SECURITY (JWT / Symfony) =================

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        // Retourne un tableau de rôles pour Symfony
        // ROLE_ELU, ROLE_CHEVALIER, ROLE_ADMIN
        return ['ROLE_' . strtoupper($this->role)];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Rien à faire ici pour l'instant
    }

    // ================= VERIFICATION FIELDS =================

    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getIdCardNumber(): ?string
    {
        return $this->idCardNumber;
    }

    public function setIdCardNumber(?string $idCardNumber): static
    {
        $this->idCardNumber = $idCardNumber;
        return $this;
    }

    public function getIdCardPhotoPath(): ?string
    {
        return $this->idCardPhotoPath;
    }

    public function setIdCardPhotoPath(?string $idCardPhotoPath): static
    {
        $this->idCardPhotoPath = $idCardPhotoPath;
        return $this;
    }

    public function getSelfieWithIdPath(): ?string
    {
        return $this->selfieWithIdPath;
    }

    public function setSelfieWithIdPath(?string $selfieWithIdPath): static
    {
        $this->selfieWithIdPath = $selfieWithIdPath;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): static
    {
        $this->verifiedBy = $verifiedBy;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    // ================= RESIDENCE & CONTACT D'URGENCE =================

    public function getResidenceAddress(): ?string
    {
        return $this->residenceAddress;
    }

    public function setResidenceAddress(?string $residenceAddress): static
    {
        $this->residenceAddress = $residenceAddress;
        return $this;
    }

    public function getEmergencyContactName(): ?string
    {
        return $this->emergencyContactName;
    }

    public function setEmergencyContactName(?string $emergencyContactName): static
    {
        $this->emergencyContactName = $emergencyContactName;
        return $this;
    }

    public function getEmergencyContactPhone(): ?string
    {
        return $this->emergencyContactPhone;
    }

    public function setEmergencyContactPhone(?string $emergencyContactPhone): static
    {
        $this->emergencyContactPhone = $emergencyContactPhone;
        return $this;
    }

    // ================= VEHICULE =================

    public function getVehicleType(): ?string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?string $vehicleType): static
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getVehicleRegistration(): ?string
    {
        return $this->vehicleRegistration;
    }

    public function setVehicleRegistration(?string $vehicleRegistration): static
    {
        $this->vehicleRegistration = $vehicleRegistration;
        return $this;
    }

    public function getVehicleCardNumber(): ?string
    {
        return $this->vehicleCardNumber;
    }

    public function setVehicleCardNumber(?string $vehicleCardNumber): static
    {
        $this->vehicleCardNumber = $vehicleCardNumber;
        return $this;
    }

    public function getVehicleBrand(): ?string
    {
        return $this->vehicleBrand;
    }

    public function setVehicleBrand(?string $vehicleBrand): static
    {
        $this->vehicleBrand = $vehicleBrand;
        return $this;
    }

    public function getVehicleModel(): ?string
    {
        return $this->vehicleModel;
    }

    public function setVehicleModel(?string $vehicleModel): static
    {
        $this->vehicleModel = $vehicleModel;
        return $this;
    }

    public function getVehicleColor(): ?string
    {
        return $this->vehicleColor;
    }

    public function setVehicleColor(?string $vehicleColor): static
    {
        $this->vehicleColor = $vehicleColor;
        return $this;
    }

    public function getVehicleDocumentsPath(): ?string
    {
        return $this->vehicleDocumentsPath;
    }

    public function setVehicleDocumentsPath(?string $vehicleDocumentsPath): static
    {
        $this->vehicleDocumentsPath = $vehicleDocumentsPath;
        return $this;
    }

    public function getSelfiePath(): ?string
    {
        return $this->selfiePath;
    }

    public function setSelfiePath(?string $selfiePath): static
    {
        $this->selfiePath = $selfiePath;
        return $this;
    }

    // ================= HELPER METHODS =================

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getVehicleDescription(): string
    {
        if (!$this->vehicleType) {
            return 'Non defini';
        }
        $desc = ucfirst($this->vehicleType);
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
