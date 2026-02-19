<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    // Type: text | offer
    #[ORM\Column(length: 20)]
    private string $type = 'text';

    // Pour les offres de prix
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $proposedPrice = null;

    // Statut de l'offre: pending | accepted | rejected (uniquement pour type=offer)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $offerStatus = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isRead = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, ['text', 'offer'])) {
            throw new \InvalidArgumentException('Type de message invalide');
        }
        $this->type = $type;
        return $this;
    }

    public function getProposedPrice(): ?float
    {
        return $this->proposedPrice;
    }

    public function setProposedPrice(?float $proposedPrice): self
    {
        $this->proposedPrice = $proposedPrice;
        return $this;
    }

    public function getOfferStatus(): ?string
    {
        return $this->offerStatus;
    }

    public function setOfferStatus(?string $offerStatus): self
    {
        if ($offerStatus !== null && !in_array($offerStatus, ['pending', 'accepted', 'rejected'])) {
            throw new \InvalidArgumentException('Statut d\'offre invalide');
        }
        $this->offerStatus = $offerStatus;
        return $this;
    }

    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
