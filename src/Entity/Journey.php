<?php

namespace App\Entity;

use App\Repository\JourneyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journey représente un trajet publié par un Chevalier
 * C'est l'annonce "Je vais de A à B à telle heure, je propose mes services"
 */
#[ORM\Entity(repositoryClass: JourneyRepository::class)]
#[ORM\Table(name: 'journeys')]
class Journey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $chevalier = null;

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

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $departureTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $arrivalTime = null;

    #[ORM\Column(type: 'float')]
    private ?float $pricePerKg = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isNegotiable = true;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $maxWeight = null; // Poids max transportable en kg (pour international)

    // Capacité de poids pour national/regional (leger, moyen, lourd, tres_lourd)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $maxPackageWeight = null;
    // leger | moyen | lourd | tres_lourd

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null; // Notes du chevalier (ex: "Je peux prendre des colis fragiles")

    #[ORM\Column(length: 20)]
    private ?string $status = 'available';
    // available | in_progress | completed | cancelled

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'available';
        $this->isNegotiable = true;
    }

    // ================= GETTERS / SETTERS =================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChevalier(): ?User
    {
        return $this->chevalier;
    }

    public function setChevalier(User $chevalier): self
    {
        $this->chevalier = $chevalier;
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
            throw new \InvalidArgumentException('Type de trajet invalide');
        }
        $this->type = $type;
        return $this;
    }

    public function getDepartureTime(): ?\DateTimeImmutable
    {
        return $this->departureTime;
    }

    public function setDepartureTime(\DateTimeImmutable $departureTime): self
    {
        $this->departureTime = $departureTime;
        return $this;
    }

    public function getArrivalTime(): ?\DateTimeImmutable
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?\DateTimeImmutable $arrivalTime): self
    {
        $this->arrivalTime = $arrivalTime;
        return $this;
    }

    public function getPricePerKg(): ?float
    {
        return $this->pricePerKg;
    }

    public function setPricePerKg(float $pricePerKg): self
    {
        $this->pricePerKg = $pricePerKg;
        return $this;
    }

    public function getIsNegotiable(): bool
    {
        return $this->isNegotiable;
    }

    public function setIsNegotiable(bool $isNegotiable): self
    {
        $this->isNegotiable = $isNegotiable;
        return $this;
    }

    public function getMaxWeight(): ?float
    {
        return $this->maxWeight;
    }

    public function setMaxWeight(?float $maxWeight): self
    {
        $this->maxWeight = $maxWeight;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['available', 'in_progress', 'completed', 'cancelled'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMaxPackageWeight(): ?string
    {
        return $this->maxPackageWeight;
    }

    public function setMaxPackageWeight(?string $maxPackageWeight): self
    {
        $validWeights = ['leger', 'moyen', 'lourd', 'tres_lourd'];
        if ($maxPackageWeight !== null && !in_array($maxPackageWeight, $validWeights)) {
            throw new \InvalidArgumentException('Poids invalide. Valeurs acceptées: leger, moyen, lourd, tres_lourd');
        }
        $this->maxPackageWeight = $maxPackageWeight;
        return $this;
    }
}
