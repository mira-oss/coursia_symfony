<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Course;
use App\Entity\Journey;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Créer des utilisateurs de test
        $elu = new User();
        $elu->setEmail('elu@test.com');
        $elu->setPassword($this->passwordHasher->hashPassword($elu, 'password123'));
        $elu->setFirstName('Jean');
        $elu->setLastName('Dupont');
        $elu->setPhone('+33612345678');
        $elu->setRole('elu');
        $elu->setNationality('FR');
        $manager->persist($elu);

        $chevalier = new User();
        $chevalier->setEmail('chevalier@test.com');
        $chevalier->setPassword($this->passwordHasher->hashPassword($chevalier, 'password123'));
        $chevalier->setFirstName('Marie');
        $chevalier->setLastName('Martin');
        $chevalier->setPhone('+33698765432');
        $chevalier->setRole('chevalier');
        $chevalier->setNationality('FR');
        $chevalier->setIsVerified(true); // Vérifié pour les tests
        $manager->persist($chevalier);

        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setFirstName('Admin');
        $admin->setLastName('Coursia');
        $admin->setPhone('+33600000000');
        $admin->setRole('admin');
        $admin->setNationality('FR');
        $manager->persist($admin);

        // Créer des courses de test avec les nouveaux champs
        $course1 = new Course();
        $course1->setTitle('Course nationale avec poids');
        $course1->setDescription('Transport de livres');
        $course1->setDepartureAddress('Paris, France');
        $course1->setDeliveryAddress('Lyon, France');
        $course1->setType('national');
        $course1->setCreatedBy($elu);
        $course1->setDeliveryDateStart(new \DateTimeImmutable('2026-02-10 10:00:00'));
        $course1->setDeliveryDateEnd(new \DateTimeImmutable('2026-02-15 18:00:00'));
        $course1->setPackageWeight('lourd');
        $course1->setIsNegotiable(true);
        $manager->persist($course1);

        $course2 = new Course();
        $course2->setTitle('Course régionale avec photo');
        $course2->setDescription('Électronique fragile');
        $course2->setDepartureAddress('Cotonou, Bénin');
        $course2->setDeliveryAddress('Lomé, Togo');
        $course2->setType('regional');
        $course2->setCreatedBy($elu);
        $course2->setDeliveryDateStart(new \DateTimeImmutable('2026-02-20 08:00:00'));
        $course2->setPackageWeight('moyen');
        $course2->setPackagePhotoPath('uploads/test_photo.jpg');
        $course2->setIsNegotiable(false);
        $manager->persist($course2);

        $course3 = new Course();
        $course3->setTitle('Course internationale');
        $course3->setDescription('Documents importants');
        $course3->setDepartureAddress('Paris, France');
        $course3->setDeliveryAddress('Cotonou, Bénin');
        $course3->setType('international');
        $course3->setCreatedBy($elu);
        $course3->setDeliveryDateStart(new \DateTimeImmutable('2026-03-01 14:00:00'));
        $course3->setDeliveryDateEnd(new \DateTimeImmutable('2026-03-10 14:00:00'));
        $course3->setPackageWeight('leger');
        $manager->persist($course3);

        // Créer des trajets de test avec les nouveaux champs
        $journey1 = new Journey();
        $journey1->setChevalier($chevalier);
        $journey1->setDepartureAddress('Paris, France');
        $journey1->setDeliveryAddress('Lyon, France');
        $journey1->setType('national');
        $journey1->setDepartureTime(new \DateTimeImmutable('2026-02-12 09:00:00'));
        $journey1->setArrivalTime(new \DateTimeImmutable('2026-02-12 15:00:00'));
        $journey1->setPricePerKg(5.0);
        $journey1->setMaxPackageWeight('tres_lourd');
        $journey1->setIsNegotiable(true);
        $journey1->setNotes('Voiture spacieuse, peut transporter colis volumineux');
        $manager->persist($journey1);

        $journey2 = new Journey();
        $journey2->setChevalier($chevalier);
        $journey2->setDepartureAddress('Cotonou, Bénin');
        $journey2->setDeliveryAddress('Lomé, Togo');
        $journey2->setType('regional');
        $journey2->setDepartureTime(new \DateTimeImmutable('2026-02-21 10:00:00'));
        $journey2->setArrivalTime(new \DateTimeImmutable('2026-02-21 14:00:00'));
        $journey2->setPricePerKg(8.0);
        $journey2->setMaxPackageWeight('moyen');
        $journey2->setIsNegotiable(false);
        $journey2->setNotes('Transport sécurisé pour objets fragiles');
        $manager->persist($journey2);

        $journey3 = new Journey();
        $journey3->setChevalier($chevalier);
        $journey3->setDepartureAddress('Paris, France');
        $journey3->setDeliveryAddress('Cotonou, Bénin');
        $journey3->setType('international');
        $journey3->setDepartureTime(new \DateTimeImmutable('2026-03-05 06:00:00'));
        $journey3->setArrivalTime(new \DateTimeImmutable('2026-03-06 20:00:00'));
        $journey3->setPricePerKg(15.0);
        $journey3->setMaxWeight(25.0);
        $journey3->setMaxPackageWeight('lourd');
        $journey3->setIsNegotiable(true);
        $journey3->setNotes('Vol direct, livraison rapide');
        $manager->persist($journey3);

        $manager->flush();

        echo "\n✅ Fixtures chargées avec succès !\n";
        echo "📧 Élu : elu@test.com / password123\n";
        echo "📧 Chevalier : chevalier@test.com / password123 (vérifié)\n";
        echo "👑 Admin : admin@test.com / admin123\n";
        echo "📦 3 Courses créées (national, régional, international)\n";
        echo "🚗 3 Trajets créés (national, régional, international)\n\n";
    }
}
