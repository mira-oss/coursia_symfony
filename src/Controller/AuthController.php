<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
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

        // Validation format téléphone : 10 chiffres commençant par 01
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if (!preg_match('/^01\d{8}$/', $phoneDigits)) {
            return $this->json(['error' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 01'], 400);
        }

        $existingPhone = $this->em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        if ($existingPhone) {
            return $this->json(['error' => 'Ce numéro de téléphone est déjà associé à un compte'], 400);
        }

        // Rôle : elu par défaut, chevalier_pending si demandé
        $requestedRole = $data['role'] ?? 'elu';
        $role = ($requestedRole === 'chevalier_pending') ? 'chevalier_pending' : 'elu';

        $user = new User();
        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, $password))
            ->setRole($role)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone($phone)
            ->setBirthDate($data['birthDate'] ?? null)
            ->setNationality($data['nationality'] ?? '');

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'Compte créé avec succès',
            'token'   => $token,
            'user' => [
                'id'        => $user->getId(),
                'email'     => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
                'role'      => $user->getRole(),
            ]
        ], 201);
    }

    // ================= LOGIN =================
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] User $user): JsonResponse
    {
        $token = $this->jwtManager->create($user);

        return $this->json([
            'token'     => $token,
            'role'      => $user->getRole(),
            'isPending' => $user->getRole() === 'chevalier_pending',
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
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'profilePhoto' => $user->getProfilePhoto()
                ? '/uploads/profile_photos/' . $user->getProfilePhoto()
                : null,
        ]);
    }

    // ================= UPLOAD PHOTO =================
    #[Route('/upload-photo', name: 'api_upload_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $file = $request->files->get('photo');
        if (!$file) {
            return $this->json(['error' => 'Aucune photo fournie'], 400);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(['error' => 'Format non supporté. Utilisez JPG, PNG ou WebP'], 400);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['error' => 'La photo ne doit pas dépasser 5 Mo'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile_photos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Supprimer l'ancienne photo
        if ($user->getProfilePhoto()) {
            $oldFile = $uploadDir . '/' . $user->getProfilePhoto();
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $ext = $file->guessExtension() ?? 'jpg';
        $fileName = 'user_' . $user->getId() . '_' . uniqid() . '.' . $ext;
        $file->move($uploadDir, $fileName);

        $user->setProfilePhoto($fileName);
        $this->em->flush();

        return $this->json([
            'message' => 'Photo mise à jour avec succès',
            'profilePhoto' => '/uploads/profile_photos/' . $fileName,
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
            $phoneDigits = preg_replace('/\D/', '', $data['phone']);
            if (!preg_match('/^01\d{8}$/', $phoneDigits)) {
                return $this->json(['error' => 'Le numéro de téléphone doit contenir 10 chiffres et commencer par 01'], 400);
            }
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

        // Envoyer l'email avec le code à 6 chiffres
        try {
            $emailMessage = (new Email())
                ->from('coursiadev@gmail.com')
                ->to($user->getEmail())
                ->subject('Coursia - Code de vérification')
                ->html(sprintf('
                    <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:40px 32px;background:#fff;border-radius:16px;border:1px solid #E5E7EB;">
                        <div style="text-align:center;margin-bottom:28px;">
                            <div style="width:60px;height:60px;background:#FFF7ED;border-radius:50%%;display:inline-flex;align-items:center;justify-content:center;font-size:28px;">🔐</div>
                        </div>
                        <h2 style="color:#111827;font-size:20px;font-weight:700;text-align:center;margin:0 0 8px;">Réinitialisation du mot de passe</h2>
                        <p style="color:#6B7280;font-size:14px;text-align:center;margin:0 0 32px;">Bonjour <strong>%s</strong>, voici votre code de vérification :</p>
                        <div style="background:#F9FAFB;border:2px dashed #F97316;border-radius:14px;padding:24px;text-align:center;margin-bottom:28px;">
                            <span style="font-size:40px;font-weight:800;letter-spacing:14px;color:#F97316;font-family:monospace;">%s</span>
                        </div>
                        <p style="color:#9CA3AF;font-size:13px;text-align:center;margin:0 0 8px;">Ce code expire dans <strong>5 minutes</strong>.</p>
                        <p style="color:#9CA3AF;font-size:12px;text-align:center;margin:0;">Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                        <hr style="border:none;border-top:1px solid #E5E7EB;margin:28px 0 16px;">
                        <p style="color:#D1D5DB;font-size:11px;text-align:center;margin:0;">L\'équipe Coursia</p>
                    </div>',
                    $user->getFirstName(),
                    $token->getCode()
                ));

            $this->mailer->send($emailMessage);
        } catch (\Exception) {
            // Log l'erreur mais ne pas bloquer l'utilisateur
        }

        return $this->json(['message' => 'Un code de réinitialisation a été envoyé à votre email']);
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

        if (strlen($newPassword) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 400);
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
