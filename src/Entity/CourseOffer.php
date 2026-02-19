<?php

namespace App\Entity;

use App\Repository\CourseOfferRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseOfferRepository::class)]
#[ORM\Table(name: 'course_offers')]
class CourseOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $chevalier = null;

    // Tarif proposé en FCFA
    #[ORM\Column(type: 'float')]
    private float $price;

    // Le Chevalier accepte-t-il la négociation ?
    #[ORM\Column(type: 'boolean')]
    private bool $isNegotiable = false;

    // Message optionnel accompagnant l'offre
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    // pending | accepted | rejected
    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending';
        $this->isNegotiable = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(Course $course): self
    {
        $this->course = $course;
        return $this;
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

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
