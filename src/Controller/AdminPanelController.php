<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Conversation;
use App\Entity\ChevalierRequest;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/admin')]
class AdminPanelController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private SmsService $smsService;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        SmsService $smsService
    ) {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
        $this->smsService = $smsService;
    }

    // ================= INSCRIPTION (Nouvel admin) =================
    #[Route('/register', name: 'admin_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $error = null;
        $data = [];

        if ($request->isMethod('POST')) {
            $data = [
                'firstName' => $request->request->get('firstName'),
                'lastName' => $request->request->get('lastName'),
                'email' => $request->request->get('email'),
                'phone' => $request->request->get('phone'),
            ];
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');

            // Validations
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['email']) || empty($data['phone'])) {
                $error = 'Veuillez remplir tous les champs obligatoires';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($data['email']), '@gmail.com')) {
                $error = 'L\'adresse email doit être au format identifiant@gmail.com';
            } elseif (empty($password)) {
                $error = 'Veuillez saisir un mot de passe';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères';
            } elseif ($password !== $confirmPassword) {
                $error = 'Les mots de passe ne correspondent pas';
            } else {
                // Vérifier si l'email existe déjà
                $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
                if ($existingUser) {
                    $error = 'Un compte avec cet email existe déjà';
                } else {
                    // Creer l'admin
                    $admin = new User();
                    $admin->setEmail($data['email']);
                    $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
                    $admin->setFirstName($data['firstName']);
                    $admin->setLastName($data['lastName']);
                    $admin->setPhone($data['phone']);
                    $admin->setNationality('');
                    $admin->setRole('admin');
                    $admin->setIsVerified(true);

                    $this->em->persist($admin);
                    $this->em->flush();

                    // Rediriger vers login avec message de succes
                    $this->addFlash('success', 'Compte créé avec succès ! Connectez-vous.');
                    return $this->redirectToRoute('admin_login');
                }
            }
        }

        return $this->render('admin/register.html.twig', [
            'error' => $error,
            'data' => $data
        ]);
    }

    // ================= LOGIN =================
    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, SessionInterface $session): Response
    {
        // Si deja connecte, rediriger vers dashboard
        if ($session->get('admin_id')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = null;
        $lastEmail = '';

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $lastEmail = $email;

            if (empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@gmail.com')) {
                $error = 'L\'adresse email doit être au format identifiant@gmail.com';
            } else {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    $error = 'Adresse email ou mot de passe incorrect';
                } elseif ($user->getRole() !== 'admin') {
                    $error = 'Accès réservé aux administrateurs';
                } elseif (!$this->passwordHasher->isPasswordValid($user, $password)) {
                    $error = 'Adresse email ou mot de passe incorrect';
                } else {
                    // Connexion réussie
                    $session->set('admin_id', $user->getId());
                    return $this->redirectToRoute('admin_dashboard');
                }
            }
        }

        return $this->render('admin/login.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail
        ]);
    }

    #[Route('/logout', name: 'admin_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('admin_id');
        return $this->redirectToRoute('admin_login');
    }

    // ================= DASHBOARD =================
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        // Periode de filtrage (default: 30 jours)
        $period = $request->query->get('period', '30');
        $dateFrom = $this->getDateFromPeriod($period);

        // Statistiques globales
        $stats = [
            'totalUsers' => $this->em->getRepository(User::class)->count([]),
            'totalChevaliers' => $this->em->getRepository(User::class)->count(['role' => 'chevalier']),
            'pendingChevaliers' => $this->em->getRepository(ChevalierRequest::class)->count(['status' => 'pending']),
            'totalCourses' => $this->em->getRepository(Course::class)->count([]),
        ];

        // Courses selon la periode
        $courseRepo = $this->em->getRepository(Course::class);
        if ($dateFrom) {
            $qb = $courseRepo->createQueryBuilder('c')
                ->where('c.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom)
                ->orderBy('c.createdAt', 'DESC')
                ->setMaxResults(10);
            $recentCourses = $qb->getQuery()->getResult();

            // Compter les courses de la periode
            $coursesCount = $courseRepo->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom)
                ->getQuery()
                ->getSingleScalarResult();
            $stats['periodCourses'] = $coursesCount;
        } else {
            $recentCourses = $courseRepo->findBy([], ['createdAt' => 'DESC'], 10);
            $stats['periodCourses'] = $stats['totalCourses'];
        }

        // Demandes en attente
        $pendingRequests = $this->em->getRepository(ChevalierRequest::class)
            ->findBy(['status' => 'pending'], ['createdAt' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'admin' => $admin,
            'stats' => $stats,
            'recentCourses' => $recentCourses,
            'pendingRequests' => $pendingRequests,
            'pending_chevaliers_count' => $stats['pendingChevaliers'],
            'currentPeriod' => $period
        ]);
    }

    /**
     * Retourne la date de debut selon la periode selectionnee
     */
    private function getDateFromPeriod(string $period): ?\DateTimeImmutable
    {
        return match($period) {
            '7' => new \DateTimeImmutable('-7 days'),
            '30' => new \DateTimeImmutable('-30 days'),
            '90' => new \DateTimeImmutable('-90 days'),
            '365' => new \DateTimeImmutable('-1 year'),
            'all' => null,
            default => new \DateTimeImmutable('-30 days'),
        };
    }

    // ================= USERS =================
    #[Route('/users', name: 'admin_users')]
    public function users(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $role = $request->query->get('role');
        $criteria = $role ? ['role' => $role] : [];

        $users = $this->em->getRepository(User::class)
            ->findBy($criteria, ['createdAt' => 'DESC']);

        return $this->render('admin/users/index.html.twig', [
            'admin' => $admin,
            'users' => $users,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    #[Route('/users/{id}', name: 'admin_users_show')]
    public function showUser(int $id, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Courses de l'utilisateur (comme elu ou chevalier)
        $coursesAsElu = $this->em->getRepository(Course::class)->findBy(['createdBy' => $user]);
        $coursesAsChevalier = $this->em->getRepository(Course::class)->findBy(['acceptedBy' => $user]);
        $courses = array_merge($coursesAsElu, $coursesAsChevalier);

        return $this->render('admin/users/show.html.twig', [
            'admin' => $admin,
            'user' => $user,
            'courses' => $courses,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    #[Route('/users/{id}/toggle', name: 'admin_users_toggle')]
    public function toggleUser(int $id, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setIsActive(!$user->getIsActive());
        $this->em->flush();

        $this->addFlash('success', $user->getIsActive() ? 'Utilisateur activé' : 'Utilisateur désactivé');

        return $this->redirectToRoute('admin_users_show', ['id' => $id]);
    }

    // ================= CHEVALIER REQUESTS =================
    #[Route('/chevaliers/pending', name: 'admin_chevaliers_pending')]
    public function pendingChevaliers(SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $requests = $this->em->getRepository(ChevalierRequest::class)
            ->findBy(['status' => 'pending'], ['createdAt' => 'DESC']);

        return $this->render('admin/chevaliers/pending.html.twig', [
            'admin' => $admin,
            'requests' => $requests,
            'pending_chevaliers_count' => count($requests)
        ]);
    }

    #[Route('/chevaliers/{id}/review', name: 'admin_chevaliers_review')]
    public function reviewChevalier(int $id, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $request = $this->em->getRepository(ChevalierRequest::class)->find($id);

        if (!$request) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        return $this->render('admin/chevaliers/review.html.twig', [
            'admin' => $admin,
            'request' => $request,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    #[Route('/chevaliers/{id}/approve', name: 'admin_chevaliers_approve', methods: ['POST'])]
    public function approveChevalier(int $id, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $chevalierRequest = $this->em->getRepository(ChevalierRequest::class)->find($id);

        if (!$chevalierRequest || $chevalierRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Demande invalide ou déjà traitée');
            return $this->redirectToRoute('admin_chevaliers_pending');
        }

        // Verifier si un user existe deja
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $chevalierRequest->getEmail()]);

        if ($existingUser) {
            // Transformer l'utilisateur existant en chevalier et copier les infos
            $this->copyRequestDataToUser($existingUser, $chevalierRequest);
            $existingUser->setRole('chevalier');
            $existingUser->setIsVerified(true);
            $existingUser->setVerifiedAt(new \DateTimeImmutable());
            $existingUser->setVerifiedBy($admin);

            $chevalierRequest->setStatus('approved');
            $chevalierRequest->setProcessedBy($admin);
            $chevalierRequest->setProcessedAt(new \DateTimeImmutable());
            $chevalierRequest->setCreatedUser($existingUser);

            $this->em->flush();

            $this->addFlash('success', 'Utilisateur promu Chevalier avec succès');
        } else {
            // Creer un nouveau compte
            $password = bin2hex(random_bytes(8));

            $chevalier = new User();
            $chevalier->setEmail($chevalierRequest->getEmail());
            $chevalier->setPassword($this->passwordHasher->hashPassword($chevalier, $password));
            $chevalier->setFirstName($chevalierRequest->getFirstName());
            $chevalier->setLastName($chevalierRequest->getLastName());
            $chevalier->setPhone($chevalierRequest->getPhone());
            $chevalier->setNationality($chevalierRequest->getNationality() ?? '');
            $chevalier->setRole('chevalier');
            $chevalier->setIsVerified(true);
            $chevalier->setVerifiedAt(new \DateTimeImmutable());
            $chevalier->setVerifiedBy($admin);

            // Copier toutes les infos de la demande
            $this->copyRequestDataToUser($chevalier, $chevalierRequest);

            $chevalierRequest->setStatus('approved');
            $chevalierRequest->setProcessedBy($admin);
            $chevalierRequest->setProcessedAt(new \DateTimeImmutable());
            $chevalierRequest->setCreatedUser($chevalier);

            $this->em->persist($chevalier);
            $this->em->flush();

            $this->addFlash('success', 'Chevalier créé avec succès. Mot de passe :' . $password);
        }

        return $this->redirectToRoute('admin_chevaliers_pending');
    }

    /**
     * Copie les donnees de la demande vers le compte utilisateur
     */
    private function copyRequestDataToUser(User $user, ChevalierRequest $request): void
    {
        // Informations d'identite
        $user->setIdCardNumber($request->getNpi());
        $user->setResidenceAddress($request->getResidenceAddress());
        $user->setEmergencyContactName($request->getEmergencyContactName());
        $user->setEmergencyContactPhone($request->getEmergencyContactPhone());

        // Informations vehicule
        $user->setVehicleType($request->getVehicleType());
        $user->setVehicleRegistration($request->getVehicleRegistration());
        $user->setVehicleCardNumber($request->getVehicleCardNumber());
        $user->setVehicleBrand($request->getVehicleBrand());
        $user->setVehicleModel($request->getVehicleModel());
        $user->setVehicleColor($request->getVehicleColor());
        $user->setVehicleDocumentsPath($request->getVehicleDocumentsPath());

        // Photos de verification
        $user->setSelfiePath($request->getSelfiePath());
        $user->setSelfieWithIdPath($request->getSelfieWithCipPath());
    }

    #[Route('/chevaliers/{id}/reject', name: 'admin_chevaliers_reject', methods: ['POST'])]
    public function rejectChevalier(int $id, Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $chevalierRequest = $this->em->getRepository(ChevalierRequest::class)->find($id);

        if (!$chevalierRequest || $chevalierRequest->getStatus() !== 'pending') {
            $this->addFlash('error', 'Demande invalide ou déjà traitée');
            return $this->redirectToRoute('admin_chevaliers_pending');
        }

        $reason = $request->request->get('reason', 'Demande rejetée');

        $chevalierRequest->setStatus('rejected');
        $chevalierRequest->setProcessedBy($admin);
        $chevalierRequest->setProcessedAt(new \DateTimeImmutable());
        $chevalierRequest->setAdminNotes($reason);

        $this->em->flush();

        $this->addFlash('success', 'Demande rejetée');

        return $this->redirectToRoute('admin_chevaliers_pending');
    }

    // ================= COURSES =================
    #[Route('/courses', name: 'admin_courses')]
    public function courses(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $criteria = [];
        if ($status = $request->query->get('status')) {
            $criteria['status'] = $status;
        }
        if ($type = $request->query->get('type')) {
            $criteria['type'] = $type;
        }

        $courses = $this->em->getRepository(Course::class)
            ->findBy($criteria, ['createdAt' => 'DESC']);

        return $this->render('admin/courses/index.html.twig', [
            'admin' => $admin,
            'courses' => $courses,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    #[Route('/courses/{id}', name: 'admin_courses_show')]
    public function showCourse(int $id, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            throw $this->createNotFoundException('Course non trouvée');
        }

        // Liste des chevaliers pour l'assignation (courses régionales)
        $chevaliers = $this->em->getRepository(User::class)->findBy(
            ['role' => 'chevalier'],
            ['firstName' => 'ASC']
        );

        return $this->render('admin/courses/show.html.twig', [
            'admin' => $admin,
            'course' => $course,
            'chevaliers' => $chevaliers,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    #[Route('/courses/{id}/assign', name: 'admin_courses_assign', methods: ['POST'])]
    public function assignChevalier(int $id, Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            throw $this->createNotFoundException('Course non trouvée');
        }

        if ($course->getType() !== 'regional') {
            $this->addFlash('error', 'Cette action est réservée aux courses régionales');
            return $this->redirectToRoute('admin_courses_show', ['id' => $id]);
        }

        if ($course->getStatus() !== 'created') {
            $this->addFlash('error', 'Cette course a déjà été traitée');
            return $this->redirectToRoute('admin_courses_show', ['id' => $id]);
        }

        $chevalierIdRaw = $request->request->get('chevalier_id');
        if (empty($chevalierIdRaw)) {
            $this->addFlash('error', 'Veuillez sélectionner un Chevalier');
            return $this->redirectToRoute('admin_courses_show', ['id' => $id]);
        }

        $chevalier = $this->em->getRepository(User::class)->find((int) $chevalierIdRaw);

        if (!$chevalier || $chevalier->getRole() !== 'chevalier') {
            $this->addFlash('error', 'Chevalier invalide');
            return $this->redirectToRoute('admin_courses_show', ['id' => $id]);
        }

        // Assigner le Chevalier et mettre à jour le statut
        $course->setAcceptedBy($chevalier);
        $course->setStatus('accepted');

        // Générer le token de livraison
        $course->generateDeliveryToken();

        $this->em->flush();

        // Envoyer le SMS de confirmation au destinataire
        $smsSent = $this->smsService->sendDeliveryConfirmationLink(
            $course->getRecipientPhone(),
            $course->getRecipientFirstName(),
            $course->getCreatedBy()->getFirstName() . ' ' . $course->getCreatedBy()->getLastName(),
            $course->getDeliveryToken()
        );

        if ($smsSent) {
            $this->addFlash('success', 'Chevalier assigné et SMS envoyé au destinataire avec succès');
        } else {
            $this->addFlash('warning', 'Chevalier assigné mais l\'envoi du SMS a échoué. Vérifiez les logs.');
        }

        return $this->redirectToRoute('admin_courses_show', ['id' => $id]);
    }

    // ================= CONVERSATIONS =================
    #[Route('/conversations', name: 'admin_conversations')]
    public function conversations(SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $conversations = $this->em->getRepository(Conversation::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/messages/index.html.twig', [
            'admin' => $admin,
            'conversations' => $conversations,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    // ================= RECHERCHE GLOBALE =================
    #[Route('/search', name: 'admin_search')]
    public function search(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $query = trim($request->query->get('q', ''));
        $results = [
            'users' => [],
            'courses' => [],
            'chevalierRequests' => [],
        ];

        if (strlen($query) >= 2) {
            // Recherche utilisateurs (nom, prénom, email)
            $results['users'] = $this->em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('LOWER(u.firstName) LIKE :q')
                ->orWhere('LOWER(u.lastName) LIKE :q')
                ->orWhere('LOWER(u.email) LIKE :q')
                ->orWhere('u.phone LIKE :q')
                ->setParameter('q', '%' . strtolower($query) . '%')
                ->orderBy('u.createdAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            // Recherche courses (par ID ou statut)
            $coursesQb = $this->em->getRepository(Course::class)
                ->createQueryBuilder('c')
                ->orderBy('c.createdAt', 'DESC')
                ->setMaxResults(20);

            if (is_numeric($query)) {
                $coursesQb->where('c.id = :id')->setParameter('id', (int) $query);
            } else {
                $coursesQb->where('LOWER(c.status) LIKE :q')
                    ->setParameter('q', '%' . strtolower($query) . '%');
            }
            $results['courses'] = $coursesQb->getQuery()->getResult();

            // Recherche demandes chevalier (nom, prénom, email)
            $results['chevalierRequests'] = $this->em->getRepository(ChevalierRequest::class)
                ->createQueryBuilder('cr')
                ->where('LOWER(cr.firstName) LIKE :q')
                ->orWhere('LOWER(cr.lastName) LIKE :q')
                ->orWhere('LOWER(cr.email) LIKE :q')
                ->setParameter('q', '%' . strtolower($query) . '%')
                ->orderBy('cr.createdAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();
        }

        $totalResults = count($results['users']) + count($results['courses']) + count($results['chevalierRequests']);

        return $this->render('admin/search.html.twig', [
            'admin' => $admin,
            'query' => $query,
            'results' => $results,
            'totalResults' => $totalResults,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    // ================= PROFIL =================
    #[Route('/profile', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'update_info') {
                $firstName = trim($request->request->get('firstName', ''));
                $lastName = trim($request->request->get('lastName', ''));
                $email = trim($request->request->get('email', ''));
                $phone = trim($request->request->get('phone', ''));

                if (empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
                    $error = 'Veuillez remplir tous les champs obligatoires';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@gmail.com')) {
                    $error = 'L\'adresse email doit être au format identifiant@gmail.com';
                } else {
                    // Vérifier si l'email est déjà pris par un autre utilisateur
                    $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existingUser && $existingUser->getId() !== $admin->getId()) {
                        $error = 'Cette adresse email est déjà utilisée par un autre compte';
                    } else {
                        $admin->setFirstName($firstName);
                        $admin->setLastName($lastName);
                        $admin->setEmail($email);
                        $admin->setPhone($phone);
                        $this->em->flush();
                        $success = 'Vos informations ont été mises à jour avec succès';
                    }
                }
            } elseif ($action === 'update_password') {
                $currentPassword = $request->request->get('currentPassword', '');
                $newPassword = $request->request->get('newPassword', '');
                $confirmPassword = $request->request->get('confirmPassword', '');

                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'Veuillez remplir tous les champs de mot de passe';
                } elseif (!$this->passwordHasher->isPasswordValid($admin, $currentPassword)) {
                    $error = 'Le mot de passe actuel est incorrect';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'Les nouveaux mots de passe ne correspondent pas';
                } else {
                    $admin->setPassword($this->passwordHasher->hashPassword($admin, $newPassword));
                    $this->em->flush();
                    $success = 'Votre mot de passe a été modifié avec succès';
                }
            }
        }

        return $this->render('admin/profile.html.twig', [
            'admin' => $admin,
            'error' => $error,
            'success' => $success,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    // ================= PARAMÈTRES =================
    #[Route('/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SessionInterface $session): Response
    {
        $admin = $this->getAdmin($session);
        if (!$admin) {
            return $this->redirectToRoute('admin_login');
        }

        $success = null;

        // Charger les paramètres depuis la session (ou valeurs par défaut)
        $settings = $session->get('admin_settings', [
            'platform_name' => 'Coursia',
            'contact_email' => 'support@coursia.com',
            'contact_phone' => '+229 00 00 00 00',
            'commission_rate' => 10,
            'min_course_price' => 500,
            'max_course_distance' => 50,
            'currency' => 'XOF',
            'email_notifications' => true,
            'new_chevalier_notification' => true,
            'new_course_notification' => true,
            'maintenance_mode' => false,
        ]);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'update_general') {
                $settings['platform_name'] = trim($request->request->get('platform_name', 'Coursia'));
                $settings['contact_email'] = trim($request->request->get('contact_email', ''));
                $settings['contact_phone'] = trim($request->request->get('contact_phone', ''));
                $settings['currency'] = $request->request->get('currency', 'XOF');
                $success = 'Paramètres généraux mis à jour avec succès';
            } elseif ($action === 'update_courses') {
                $settings['commission_rate'] = (int) $request->request->get('commission_rate', 10);
                $settings['min_course_price'] = (int) $request->request->get('min_course_price', 500);
                $settings['max_course_distance'] = (int) $request->request->get('max_course_distance', 50);
                $success = 'Paramètres des courses mis à jour avec succès';
            } elseif ($action === 'update_notifications') {
                $settings['email_notifications'] = $request->request->has('email_notifications');
                $settings['new_chevalier_notification'] = $request->request->has('new_chevalier_notification');
                $settings['new_course_notification'] = $request->request->has('new_course_notification');
                $success = 'Paramètres de notifications mis à jour avec succès';
            } elseif ($action === 'update_maintenance') {
                $settings['maintenance_mode'] = $request->request->has('maintenance_mode');
                $success = $settings['maintenance_mode']
                    ? 'Mode maintenance activé'
                    : 'Mode maintenance désactivé';
            }

            $session->set('admin_settings', $settings);
        }

        return $this->render('admin/settings.html.twig', [
            'admin' => $admin,
            'settings' => $settings,
            'success' => $success,
            'pending_chevaliers_count' => $this->getPendingChevalierCount()
        ]);
    }

    // ================= HELPERS =================
    private function getAdmin(SessionInterface $session): ?User
    {
        $adminId = $session->get('admin_id');
        if (!$adminId) {
            return null;
        }

        $admin = $this->em->getRepository(User::class)->find($adminId);

        if (!$admin || $admin->getRole() !== 'admin') {
            $session->remove('admin_id');
            return null;
        }

        return $admin;
    }

    private function getPendingChevalierCount(): int
    {
        return $this->em->getRepository(ChevalierRequest::class)->count(['status' => 'pending']);
    }
}
