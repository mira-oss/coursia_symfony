<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'courses')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $departureAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $deliveryAddress = null;

    // Coordonnées GPS pour la carte
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLatitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $departureLongitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $deliveryLatitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $deliveryLongitude = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;
    // national | regional | international

    #[ORM\Column(length: 20)]
    private ?string $status = 'created';
    // created | accepted | started | finished

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;
    // elu

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $acceptedBy = null;
    // chevalier

    // Informations du destinataire (obligatoire pour tous les types de courses)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $recipientLastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $recipientFirstName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $recipientPhone = null;

    // Token unique envoyé par SMS pour confirmation de livraison (courses régionales)
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $deliveryToken = null;

    // Date/heure de confirmation par le destinataire via le lien SMS
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveryConfirmedAt = null;

    // Intervalle de livraison souhaité par l'Élu
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveryDateStart = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveryDateEnd = null;

    // Type de colis
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $packageType = null;
    // documents | vetements | alimentaire | electronique | medicaments | fragile | autres

    // Poids en kg (facultatif pour national/régional, obligatoire pour international)
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $packageWeight = null;

    // Photo du colis (optionnel) - ancienne version single photo
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packagePhotoPath = null;

    // Photos du colis (multiple photos)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = [];

    // Code de récupération (donné à l'expéditeur, entré par le Chevalier)
    #[ORM\Column(length: 6, nullable: true)]
    private ?string $pickupCode = null;

    // Code de livraison (donné au destinataire, entré par le Chevalier)
    #[ORM\Column(length: 6, nullable: true)]
    private ?string $deliveryCode = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'created';
    }

    // ================= GETTERS / SETTERS =================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDepartureAddress(): ?string
    {
        return $this->departureAddress;
    }

    public function setDepartureAddress(string $departureAddress): self
    {
        $this->departureAddress = $departureAddress;
        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): self
    {
        $this->deliveryAddress = $deliveryAddress;
        return $this;
    }

    public function getDepartureLatitude(): ?float
    {
        return $this->departureLatitude;
    }

    public function setDepartureLatitude(?float $departureLatitude): self
    {
        $this->departureLatitude = $departureLatitude;
        return $this;
    }

    public function getDepartureLongitude(): ?float
    {
        return $this->departureLongitude;
    }

    public function setDepartureLongitude(?float $departureLongitude): self
    {
        $this->departureLongitude = $departureLongitude;
        return $this;
    }

    public function getDeliveryLatitude(): ?float
    {
        return $this->deliveryLatitude;
    }

    public function setDeliveryLatitude(?float $deliveryLatitude): self
    {
        $this->deliveryLatitude = $deliveryLatitude;
        return $this;
    }

    public function getDeliveryLongitude(): ?float
    {
        return $this->deliveryLongitude;
    }

    public function setDeliveryLongitude(?float $deliveryLongitude): self
    {
        $this->deliveryLongitude = $deliveryLongitude;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, ['national', 'regional', 'international'])) {
            throw new \InvalidArgumentException('Type de course invalide');
        }
        $this->type = $type;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['created', 'accepted', 'started', 'delivered', 'finished', 'cancelled'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getAcceptedBy(): ?User
    {
        return $this->acceptedBy;
    }

    public function setAcceptedBy(?User $user): self
    {
        $this->acceptedBy = $user;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeliveryDateStart(): ?\DateTimeImmutable
    {
        return $this->deliveryDateStart;
    }

    public function setDeliveryDateStart(?\DateTimeImmutable $deliveryDateStart): self
    {
        $this->deliveryDateStart = $deliveryDateStart;
        return $this;
    }

    public function getDeliveryDateEnd(): ?\DateTimeImmutable
    {
        return $this->deliveryDateEnd;
    }

    public function setDeliveryDateEnd(?\DateTimeImmutable $deliveryDateEnd): self
    {
        $this->deliveryDateEnd = $deliveryDateEnd;
        return $this;
    }

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(?string $packageType): self
    {
        $valid = ['documents', 'vetements', 'alimentaire', 'electronique', 'medicaments', 'fragile', 'autres'];
        if ($packageType !== null && !in_array($packageType, $valid)) {
            throw new \InvalidArgumentException('Type de colis invalide');
        }
        $this->packageType = $packageType;
        return $this;
    }

    public function getPackageWeight(): ?float
    {
        return $this->packageWeight;
    }

    public function setPackageWeight(?float $packageWeight): self
    {
        $this->packageWeight = $packageWeight;
        return $this;
    }

    public function getRecipientLastName(): ?string
    {
        return $this->recipientLastName;
    }

    public function setRecipientLastName(?string $recipientLastName): self
    {
        $this->recipientLastName = $recipientLastName;
        return $this;
    }

    public function getRecipientFirstName(): ?string
    {
        return $this->recipientFirstName;
    }

    public function setRecipientFirstName(?string $recipientFirstName): self
    {
        $this->recipientFirstName = $recipientFirstName;
        return $this;
    }

    public function getRecipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function setRecipientPhone(?string $recipientPhone): self
    {
        $this->recipientPhone = $recipientPhone;
        return $this;
    }

    public function getDeliveryToken(): ?string
    {
        return $this->deliveryToken;
    }

    public function setDeliveryToken(?string $deliveryToken): self
    {
        $this->deliveryToken = $deliveryToken;
        return $this;
    }

    public function generateDeliveryToken(): self
    {
        $this->deliveryToken = bin2hex(random_bytes(32));
        return $this;
    }

    public function getDeliveryConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->deliveryConfirmedAt;
    }

    public function setDeliveryConfirmedAt(?\DateTimeImmutable $deliveryConfirmedAt): self
    {
        $this->deliveryConfirmedAt = $deliveryConfirmedAt;
        return $this;
    }

    public function getPackagePhotoPath(): ?string
    {
        return $this->packagePhotoPath;
    }

    public function setPackagePhotoPath(?string $packagePhotoPath): self
    {
        $this->packagePhotoPath = $packagePhotoPath;
        return $this;
    }

    public function getPhotos(): array
    {
        return $this->photos ?? [];
    }

    public function setPhotos(?array $photos): self
    {
        $this->photos = $photos;
        return $this;
    }

    public function addPhoto(string $photoPath): self
    {
        $photos = $this->photos ?? [];
        $photos[] = $photoPath;
        $this->photos = $photos;
        return $this;
    }

    public function getPickupCode(): ?string
    {
        return $this->pickupCode;
    }

    public function setPickupCode(?string $pickupCode): self
    {
        $this->pickupCode = $pickupCode;
        return $this;
    }

    public function getDeliveryCode(): ?string
    {
        return $this->deliveryCode;
    }

    public function setDeliveryCode(?string $deliveryCode): self
    {
        $this->deliveryCode = $deliveryCode;
        return $this;
    }

    public function generatePickupCode(): self
    {
        $this->pickupCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        return $this;
    }

    public function generateDeliveryCode(): self
    {
        $this->deliveryCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        return $this;
    }
}
