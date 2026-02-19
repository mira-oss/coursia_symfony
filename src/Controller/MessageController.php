<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Course;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class MessageController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Lister les conversations de l'utilisateur
     */
    #[Route('/conversations', name: 'api_conversations_list', methods: ['GET'])]
    public function listConversations(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $conversations = $this->em->getRepository(Conversation::class)
            ->findByUser($user);

        $result = [];
        foreach ($conversations as $conv) {
            $other = $conv->getOtherParticipant($user);
            $lastMessage = $conv->getMessages()->last();

            $result[] = [
                'id' => $conv->getId(),
                'participant' => [
                    'id' => $other->getId(),
                    'name' => $other->getFirstName() . ' ' . $other->getLastName(),
                ],
                'course' => $conv->getCourse() ? [
                    'id' => $conv->getCourse()->getId(),
                    'title' => $conv->getCourse()->getTitle(),
                ] : null,
                'lastMessage' => $lastMessage ? [
                    'content' => $lastMessage->getContent(),
                    'type' => $lastMessage->getType(),
                    'isRead' => $lastMessage->getIsRead(),
                    'createdAt' => $lastMessage->getCreatedAt()->format('Y-m-d H:i:s'),
                    'isMine' => $lastMessage->getSender()->getId() === $user->getId(),
                ] : null,
                'updatedAt' => $conv->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(['conversations' => $result]);
    }

    /**
     * Créer ou récupérer une conversation avec un utilisateur
     */
    #[Route('/conversations', name: 'api_conversations_create', methods: ['POST'])]
    public function createConversation(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['participantId'])) {
            return $this->json(['error' => 'participantId requis'], 400);
        }

        $participant = $this->em->getRepository(User::class)->find($data['participantId']);
        if (!$participant) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        if ($participant->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas vous envoyer un message à vous-même'], 400);
        }

        // Vérifier si une conversation existe déjà
        $courseId = $data['courseId'] ?? null;
        $existingConv = $this->em->getRepository(Conversation::class)
            ->findBetweenUsers($user, $participant, $courseId);

        if ($existingConv) {
            return $this->json([
                'message' => 'Conversation existante',
                'conversation' => ['id' => $existingConv->getId()]
            ]);
        }

        // Créer la conversation
        $conversation = new Conversation();
        $conversation->setParticipant1($user);
        $conversation->setParticipant2($participant);

        if ($courseId) {
            $course = $this->em->getRepository(Course::class)->find($courseId);
            if ($course) {
                $conversation->setCourse($course);
            }
        }

        $this->em->persist($conversation);
        $this->em->flush();

        return $this->json([
            'message' => 'Conversation créée',
            'conversation' => ['id' => $conversation->getId()]
        ], 201);
    }

    /**
     * Récupérer les messages d'une conversation
     */
    #[Route('/conversations/{id}/messages', name: 'api_conversations_messages', methods: ['GET'])]
    public function getMessages(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $conversation = $this->em->getRepository(Conversation::class)->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation introuvable'], 404);
        }

        if (!$conversation->hasParticipant($user)) {
            return $this->json(['error' => 'Vous ne participez pas à cette conversation'], 403);
        }

        // Marquer les messages reçus comme lus
        foreach ($conversation->getMessages() as $msg) {
            if ($msg->getSender()->getId() !== $user->getId() && !$msg->getIsRead()) {
                $msg->setIsRead(true);
            }
        }
        $this->em->flush();

        $messages = [];
        foreach ($conversation->getMessages() as $msg) {
            $messages[] = $this->formatMessage($msg, $user);
        }

        return $this->json([
            'conversation' => [
                'id' => $conversation->getId(),
                'participant' => [
                    'id' => $conversation->getOtherParticipant($user)->getId(),
                    'name' => $conversation->getOtherParticipant($user)->getFirstName() . ' ' . $conversation->getOtherParticipant($user)->getLastName(),
                ],
                'course' => $conversation->getCourse() ? [
                    'id' => $conversation->getCourse()->getId(),
                    'title' => $conversation->getCourse()->getTitle(),
                    'status' => $conversation->getCourse()->getStatus(),
                    'price' => $conversation->getCourse()->getPrice(),
                ] : null,
            ],
            'messages' => $messages
        ]);
    }

    /**
     * Envoyer un message dans une conversation
     */
    #[Route('/conversations/{id}/messages', name: 'api_conversations_send', methods: ['POST'])]
    public function sendMessage(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $conversation = $this->em->getRepository(Conversation::class)->find($id);

        if (!$conversation) {
            return $this->json(['error' => 'Conversation introuvable'], 404);
        }

        if (!$conversation->hasParticipant($user)) {
            return $this->json(['error' => 'Vous ne participez pas à cette conversation'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['content'])) {
            return $this->json(['error' => 'Le message ne peut pas être vide'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent($data['content']);

        // Si c'est une offre de prix
        if (!empty($data['type']) && $data['type'] === 'offer') {
            if (empty($data['proposedPrice']) || $data['proposedPrice'] <= 0) {
                return $this->json(['error' => 'Prix proposé invalide'], 400);
            }

            // Vérifier que la conversation est liée à une course
            if (!$conversation->getCourse()) {
                return $this->json(['error' => 'Impossible de faire une offre sans course associée'], 400);
            }

            // Vérifier que la course est négociable
            if (!$conversation->getCourse()->getIsNegotiable()) {
                return $this->json(['error' => 'Le prix de cette course n\'est pas négociable'], 400);
            }

            $message->setType('offer');
            $message->setProposedPrice((float) $data['proposedPrice']);
            $message->setOfferStatus('pending');
        }

        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($message);
        $this->em->flush();

        return $this->json([
            'message' => 'Message envoyé',
            'data' => $this->formatMessage($message, $user)
        ], 201);
    }

    /**
     * Accepter une offre de prix
     */
    #[Route('/messages/{id}/accept-offer', name: 'api_messages_accept_offer', methods: ['POST'])]
    public function acceptOffer(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $message = $this->em->getRepository(Message::class)->find($id);

        if (!$message) {
            return $this->json(['error' => 'Message introuvable'], 404);
        }

        if ($message->getType() !== 'offer') {
            return $this->json(['error' => 'Ce message n\'est pas une offre'], 400);
        }

        if ($message->getOfferStatus() !== 'pending') {
            return $this->json(['error' => 'Cette offre a déjà été traitée'], 400);
        }

        // Seul le destinataire peut accepter (pas l'émetteur)
        if ($message->getSender()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas accepter votre propre offre'], 400);
        }

        $conversation = $message->getConversation();
        if (!$conversation->hasParticipant($user)) {
            return $this->json(['error' => 'Vous ne participez pas à cette conversation'], 403);
        }

        // Accepter l'offre
        $message->setOfferStatus('accepted');

        // Mettre à jour le prix de la course
        $course = $conversation->getCourse();
        if ($course) {
            $course->setPrice($message->getProposedPrice());

            // Si le Chevalier a fait l'offre et l'Élu accepte → course acceptée
            if ($message->getSender()->getRole() === 'chevalier' && $course->getStatus() === 'created') {
                $course->setStatus('accepted');
                $course->setAcceptedBy($message->getSender());
            }
        }

        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json([
            'message' => 'Offre acceptée ! Prix fixé à ' . $message->getProposedPrice() . '€',
            'price' => $message->getProposedPrice(),
            'courseStatus' => $course?->getStatus(),
        ]);
    }

    /**
     * Refuser une offre de prix
     */
    #[Route('/messages/{id}/reject-offer', name: 'api_messages_reject_offer', methods: ['POST'])]
    public function rejectOffer(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $message = $this->em->getRepository(Message::class)->find($id);

        if (!$message) {
            return $this->json(['error' => 'Message introuvable'], 404);
        }

        if ($message->getType() !== 'offer') {
            return $this->json(['error' => 'Ce message n\'est pas une offre'], 400);
        }

        if ($message->getOfferStatus() !== 'pending') {
            return $this->json(['error' => 'Cette offre a déjà été traitée'], 400);
        }

        if ($message->getSender()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas refuser votre propre offre'], 400);
        }

        $conversation = $message->getConversation();
        if (!$conversation->hasParticipant($user)) {
            return $this->json(['error' => 'Vous ne participez pas à cette conversation'], 403);
        }

        $message->setOfferStatus('rejected');
        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->json([
            'message' => 'Offre refusée',
        ]);
    }

    /**
     * Nombre de messages non lus
     */
    #[Route('/messages/unread-count', name: 'api_messages_unread', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $count = $this->em->getRepository(Message::class)
            ->countUnreadForUser($user);

        return $this->json(['unreadCount' => $count]);
    }

    private function formatMessage(Message $message, User $currentUser): array
    {
        return [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'type' => $message->getType(),
            'proposedPrice' => $message->getProposedPrice(),
            'offerStatus' => $message->getOfferStatus(),
            'isRead' => $message->getIsRead(),
            'isMine' => $message->getSender()->getId() === $currentUser->getId(),
            'sender' => [
                'id' => $message->getSender()->getId(),
                'name' => $message->getSender()->getFirstName() . ' ' . $message->getSender()->getLastName(),
            ],
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
