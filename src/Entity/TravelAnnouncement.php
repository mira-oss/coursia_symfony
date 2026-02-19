<?php

namespace App\Entity;

use App\Repository\TravelAnnouncementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TravelAnnouncementRepository::class)]
#[ORM\Table(name: 'travel_announcements')]
class TravelAnnouncement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Le Chevalier qui voyage
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $chevalier = null;

    #[ORM\Column(length: 100)]
    private ?string $departureCity = null;

    #[ORM\Column(length: 100)]
    private ?string $departureCountry = null;

    #[ORM\Column(length: 100)]
    private ?string $arrivalCity = null;

    #[ORM\Column(length: 100)]
    private ?string $arrivalCountry = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $travelDate = null;

    // Poids total disponible en kg
    #[ORM\Column(type: 'float')]
    private float $availableWeight;

    // Poids encore disponible (diminue avec les réservations)
    #[ORM\Column(type: 'float')]
    private float $remainingWeight;

    // Tarif par kg en FCFA
    #[ORM\Column(type: 'float')]
    private float $pricePerKg;

    // Documents de voyage
    #[ORM\Column(length: 255)]
    private ?string $passportPath = null;

    #[ORM\Column(length: 255)]
    private ?string $flightTicketPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $visaPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $boardingPassPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $baggageProofPath = null;

    // pending | approved | rejected | completed
    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectedReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending';
    }

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

    public function getDepartureCity(): ?string
    {
        return $this->departureCity;
    }

    public function setDepartureCity(string $departureCity): self
    {
        $this->departureCity = $departureCity;
        return $this;
    }

    public function getDepartureCountry(): ?string
    {
        return $this->departureCountry;
    }

    public function setDepartureCountry(string $departureCountry): self
    {
        $this->departureCountry = $departureCountry;
        return $this;
    }

    public function getArrivalCity(): ?string
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(string $arrivalCity): self
    {
        $this->arrivalCity = $arrivalCity;
        return $this;
    }

    public function getArrivalCountry(): ?string
    {
        return $this->arrivalCountry;
    }

    public function setArrivalCountry(string $arrivalCountry): self
    {
        $this->arrivalCountry = $arrivalCountry;
        return $this;
    }

    public function getTravelDate(): ?\DateTimeImmutable
    {
        return $this->travelDate;
    }

    public function setTravelDate(\DateTimeImmutable $travelDate): self
    {
        $this->travelDate = $travelDate;
        return $this;
    }

    public function getAvailableWeight(): float
    {
        return $this->availableWeight;
    }

    public function setAvailableWeight(float $availableWeight): self
    {
        $this->availableWeight = $availableWeight;
        return $this;
    }

    public function getRemainingWeight(): float
    {
        return $this->remainingWeight;
    }

    public function setRemainingWeight(float $remainingWeight): self
    {
        $this->remainingWeight = $remainingWeight;
        return $this;
    }

    public function getPricePerKg(): float
    {
        return $this->pricePerKg;
    }

    public function setPricePerKg(float $pricePerKg): self
    {
        $this->pricePerKg = $pricePerKg;
        return $this;
    }

    public function getPassportPath(): ?string
    {
        return $this->passportPath;
    }

    public function setPassportPath(string $passportPath): self
    {
        $this->passportPath = $passportPath;
        return $this;
    }

    public function getFlightTicketPath(): ?string
    {
        return $this->flightTicketPath;
    }

    public function setFlightTicketPath(string $flightTicketPath): self
    {
        $this->flightTicketPath = $flightTicketPath;
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

    public function getBoardingPassPath(): ?string
    {
        return $this->boardingPassPath;
    }

    public function setBoardingPassPath(?string $boardingPassPath): self
    {
        $this->boardingPassPath = $boardingPassPath;
        return $this;
    }

    public function getBaggageProofPath(): ?string
    {
        return $this->baggageProofPath;
    }

    public function setBaggageProofPath(?string $baggageProofPath): self
    {
        $this->baggageProofPath = $baggageProofPath;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['pending', 'approved', 'rejected', 'completed'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->status = $status;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectedReason(): ?string
    {
        return $this->rejectedReason;
    }

    public function setRejectedReason(?string $rejectedReason): self
    {
        $this->rejectedReason = $rejectedReason;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
