<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $repo
    ) {}

    /**
     * Lister mes notifications
     * GET /api/notifications
     */
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $notifications = $this->repo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            50
        );

        $unreadCount = count(array_filter(
            $notifications,
            fn($n) => !$n->getIsRead()
        ));

        return $this->json([
            'notifications' => array_map(fn($n) => $this->format($n), $notifications),
            'unreadCount'   => $unreadCount,
        ]);
    }

    /**
     * Marquer une notification comme lue
     * PATCH /api/notifications/{id}/read
     */
    #[Route('/{id}/read', name: 'api_notifications_read', methods: ['PATCH'])]
    public function markRead(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $notification = $this->repo->find($id);

        if (!$notification || $notification->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification introuvable'], 404);
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return $this->json(['message' => 'Notification marquée comme lue']);
    }

    /**
     * Tout marquer comme lu
     * PATCH /api/notifications/read-all
     */
    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['PATCH'])]
    public function markAllRead(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', ':true')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :false')
            ->setParameter('true', true)
            ->setParameter('false', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return $this->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    private function format(Notification $n): array
    {
        return [
            'id'        => $n->getId(),
            'type'      => $n->getType(),
            'title'     => $n->getTitle(),
            'message'   => $n->getMessage(),
            'data'      => $n->getData(),
            'isRead'    => $n->getIsRead(),
            'createdAt' => $n->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
