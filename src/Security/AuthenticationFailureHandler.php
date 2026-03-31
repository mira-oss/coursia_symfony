<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        // Email introuvable
        if ($exception instanceof UserNotFoundException) {
            return new JsonResponse(['error' => 'Aucun compte associé à cet email'], 401);
        }

        // Vérification manuelle si l'email existe (pour les exceptions BadCredentials)
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if ($email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                return new JsonResponse(['error' => 'Aucun compte associé à cet email'], 401);
            }
        }

        return new JsonResponse(['error' => 'Mot de passe incorrect'], 401);
    }
}
