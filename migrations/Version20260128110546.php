<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128110546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD residence_address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD emergency_contact_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD emergency_contact_phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_registration VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_card_number VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_brand VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_model VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_color VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD vehicle_documents_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD selfie_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP residence_address');
        $this->addSql('ALTER TABLE "user" DROP emergency_contact_name');
        $this->addSql('ALTER TABLE "user" DROP emergency_contact_phone');
        $this->addSql('ALTER TABLE "user" DROP vehicle_type');
        $this->addSql('ALTER TABLE "user" DROP vehicle_registration');
        $this->addSql('ALTER TABLE "user" DROP vehicle_card_number');
        $this->addSql('ALTER TABLE "user" DROP vehicle_brand');
        $this->addSql('ALTER TABLE "user" DROP vehicle_model');
        $this->addSql('ALTER TABLE "user" DROP vehicle_color');
        $this->addSql('ALTER TABLE "user" DROP vehicle_documents_path');
        $this->addSql('ALTER TABLE "user" DROP selfie_path');
    }
}
