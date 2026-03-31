<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTTokenManagerInterface $jwtManager;
    private MailerInterface $mailer;
    private PasswordResetTokenRepository $tokenRepository;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        MailerInterface $mailer,
        PasswordResetTokenRepository $tokenRepository
    ) {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
        $this->mailer = $mailer;
        $this->tokenRepository = $tokenRepository;
    }

    // ================= REGISTER =================
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $phone = $data['phone'] ?? null;

        // Validation des champs obligatoires
        if (!$email || !$password || !$firstName || !$lastName || !$phone) {
            return $this->json([
                'error' => 'Champs obligatoires: email, password, firstName, lastName, phone'
            ], 400);
        }

        // Validation format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Format d\'email invalide'], 400);
        }

        // Validation longueur mot de passe
        if (strlen($password) < 6) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 6 caractères'], 400);
        }

        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['error' => 'Cet email est déjà associé à un compte'], 400);
        }

        $existingPhone = $this->em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        if ($existingPhone) {
            return $this->json(['error' => 'Ce numéro de téléphone est déjà associé à un compte'], 400);
        }

        $user = new User();
        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, $password))
            ->setRole('elu') // Toujours "elu" à l'inscription (chevalier via demande admin)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone($phone)
            ->setBirthDate($data['birthDate'] ?? null)
            ->setNationality($data['nationality'] ?? '');

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'message' => 'Compte créé avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'role' => $user->getRole(),
            ]
        ], 201);
    }

    // ================= LOGIN =================
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] User $user): JsonResponse
    {
        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'role' => $user->getRole()
        ]);
    }

    // ================= GET ME =================
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole(),
            'isActive' => $user->getIsActive(),
            'birthDate' => $user->getBirthDate(),
            'phone' => $user->getPhone(),
            'nationality' => $user->getNationality(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    // ================= UPDATE PROFILE =================
    #[Route('/update', name: 'api_update_profile', methods: ['PUT'])]
    public function update(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'message' => 'Profil mis à jour avec succès',
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'role' => $user->getRole(),
            'isActive' => $user->getIsActive(),
            'birthDate' => $user->getBirthDate(),
            'nationality' => $user->getNationality(),
            'createdAt' => $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i:s') : null
        ]);
    }

    // ================= FORGOT PASSWORD =================
    #[Route('/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email requis'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        // Toujours retourner succès pour ne pas révéler si l'email existe
        if (!$user) {
            return $this->json([
                'message' => 'Si cet email existe, un code de réinitialisation a été envoyé'
            ]);
        }

        // Invalider les anciens tokens
        $this->tokenRepository->invalidateAllForUser($user);

        // Créer un nouveau token
        $token = new PasswordResetToken();
        $token->setUser($user);

        $this->em->persist($token);
        $this->em->flush();

        // Envoyer l'email
        try {
            $emailMessage = (new Email())
                ->from('noreply@coursia.com')
                ->to($user->getEmail())
                ->subject('Coursia - Réinitialisation de mot de passe')
                ->html(sprintf(
                    '<h2>Réinitialisation de mot de passe</h2>
                    <p>Bonjour %s,</p>
                    <p>Votre code de réinitialisation est : <strong>%s</strong></p>
                    <p>Ce code expire dans 1 heure.</p>
                    <p>Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                    <p>L\'équipe Coursia</p>',
                    $user->getFirstName(),
                    $token->getCode()
                ));

            $this->mailer->send($emailMessage);
        } catch (\Exception) {
            // Log l'erreur mais ne pas bloquer l'utilisateur
        }

        $response = ['message' => 'Un code de réinitialisation a été envoyé à votre email'];

        // En développement, retourner le code directement (mailer non configuré)
        if ($_ENV['APP_ENV'] === 'dev') {
            $response['dev_code'] = $token->getCode();
        }

        return $this->json($response);
    }

    // ================= RESET PASSWORD =================
    #[Route('/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (!$email || !$code || !$newPassword) {
            return $this->json(['error' => 'Email, code et nouveau mot de passe requis'], 400);
        }

        if (strlen($newPassword) < 6) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 6 caractères'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Code invalide ou expiré'], 400);
        }

        $token = $this->tokenRepository->findValidToken($user, $code);

        if (!$token) {
            return $this->json(['error' => 'Code invalide ou expiré'], 400);
        }

        // Mettre à jour le mot de passe
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

        // Marquer le token comme utilisé
        $token->setUsed(true);

        $this->em->flush();

        return $this->json([
            'message' => 'Mot de passe réinitialisé avec succès'
        ]);
    }
}
