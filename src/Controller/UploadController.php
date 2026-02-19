<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api')]
class UploadController extends AbstractController
{
    private EntityManagerInterface $em;
    private string $uploadsDir;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->uploadsDir = __DIR__ . '/../../public/uploads';
    }

    // ================= UPLOAD AVATAR =================
    #[Route('/users/avatar', name: 'api_upload_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 401);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier envoyé'], 400);
        }

        // Validation
        $validationError = $this->validateImage($file);
        if ($validationError) {
            return $this->json(['error' => $validationError], 400);
        }

        // Supprimer l'ancien avatar si existant
        if ($user->getAvatar()) {
            $oldPath = $this->uploadsDir . '/avatars/' . $user->getAvatar();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Générer un nom unique
        $filename = 'avatar_' . $user->getId() . '_' . time() . '.' . $file->guessExtension();

        // Déplacer le fichier
        $file->move($this->uploadsDir . '/avatars', $filename);

        // Mettre à jour l'utilisateur
        $user->setAvatar($filename);
        $this->em->flush();

        return $this->json([
            'message' => 'Avatar mis à jour',
            'avatar' => $filename,
            'url' => '/api/uploads/avatars/' . $filename
        ]);
    }

    // ================= UPLOAD PHOTOS COLIS =================
    #[Route('/courses/{id}/photos', name: 'api_upload_course_photos', methods: ['POST'])]
    public function uploadCoursePhotos(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course non trouvée'], 404);
        }

        // Seul le créateur peut ajouter des photos
        if ($course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Seul le créateur peut ajouter des photos'], 403);
        }

        // Récupérer les fichiers (peut être un seul ou plusieurs)
        $files = $request->files->get('photos');

        // Si c'est un seul fichier, le mettre dans un tableau
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!$files || count($files) === 0) {
            return $this->json(['error' => 'Aucun fichier envoyé'], 400);
        }

        // Limite de 5 photos par course
        $currentPhotos = $course->getPhotos();
        if (count($currentPhotos) + count($files) > 5) {
            return $this->json(['error' => 'Maximum 5 photos par course'], 400);
        }

        $uploadedPhotos = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Validation
            $validationError = $this->validateImage($file);
            if ($validationError) {
                return $this->json(['error' => $validationError], 400);
            }

            // Générer un nom unique
            $filename = 'course_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file->guessExtension();

            // Déplacer le fichier
            $file->move($this->uploadsDir . '/courses', $filename);

            // Ajouter à la course
            $course->addPhoto($filename);
            $uploadedPhotos[] = [
                'filename' => $filename,
                'url' => '/api/uploads/courses/' . $filename
            ];
        }

        $this->em->flush();

        return $this->json([
            'message' => count($uploadedPhotos) . ' photo(s) ajoutée(s)',
            'photos' => $uploadedPhotos,
            'total' => count($course->getPhotos())
        ]);
    }

    // ================= GET IMAGE =================
    #[Route('/uploads/{folder}/{filename}', name: 'api_get_upload', methods: ['GET'])]
    public function getUpload(string $folder, string $filename): BinaryFileResponse|JsonResponse
    {
        // Valider le dossier
        if (!in_array($folder, ['avatars', 'courses'])) {
            return $this->json(['error' => 'Dossier invalide'], 400);
        }

        $filePath = $this->uploadsDir . '/' . $folder . '/' . $filename;

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Fichier non trouvé'], 404);
        }

        return new BinaryFileResponse($filePath);
    }

    // ================= DELETE COURSE PHOTO =================
    #[Route('/courses/{id}/photos/{filename}', name: 'api_delete_course_photo', methods: ['DELETE'])]
    public function deleteCoursePhoto(int $id, string $filename, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non autorisé'], 401);
        }

        $course = $this->em->getRepository(Course::class)->find($id);

        if (!$course) {
            return $this->json(['error' => 'Course non trouvée'], 404);
        }

        // Seul le créateur peut supprimer des photos
        if ($course->getCreatedBy()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Seul le créateur peut supprimer des photos'], 403);
        }

        $photos = $course->getPhotos();
        $key = array_search($filename, $photos);

        if ($key === false) {
            return $this->json(['error' => 'Photo non trouvée'], 404);
        }

        // Supprimer le fichier physique
        $filePath = $this->uploadsDir . '/courses/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Retirer de la liste
        unset($photos[$key]);
        $course->setPhotos(array_values($photos));

        $this->em->flush();

        return $this->json([
            'message' => 'Photo supprimée',
            'remaining' => count($course->getPhotos())
        ]);
    }

    // ================= HELPER: VALIDATE IMAGE =================
    private function validateImage(UploadedFile $file): ?string
    {
        // Types autorisés
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, WEBP';
        }

        // Taille max: 5 Mo
        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return 'Fichier trop volumineux. Maximum 5 Mo';
        }

        return null;
    }
}
