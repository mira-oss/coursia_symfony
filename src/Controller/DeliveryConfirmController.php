<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DeliveryConfirmController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Page web accessible par le destinataire via le lien SMS
     * GET /confirm/{token}
     */
    #[Route('/confirm/{token}', name: 'delivery_confirm', methods: ['GET'])]
    public function confirm(string $token): Response
    {
        $course = $this->em->getRepository(Course::class)
            ->findOneBy(['deliveryToken' => $token]);

        // Token invalide ou déjà utilisé
        if (!$course) {
            return $this->render('confirm/invalid.html.twig');
        }

        if ($course->getDeliveryConfirmedAt() !== null) {
            return $this->render('confirm/already_confirmed.html.twig', [
                'confirmedAt' => $course->getDeliveryConfirmedAt(),
            ]);
        }

        // Confirmer la livraison
        $course->setDeliveryConfirmedAt(new \DateTimeImmutable());
        $course->setStatus('finished');
        $this->em->flush();

        return $this->render('confirm/success.html.twig', [
            'course' => $course,
        ]);
    }
}
