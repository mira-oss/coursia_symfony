<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316080928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chevalier_requests ADD residence_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD emergency_contact_phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD carte_grise_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chevalier_requests DROP residence_address');
        $this->addSql('ALTER TABLE chevalier_requests DROP emergency_contact_phone');
        $this->addSql('ALTER TABLE chevalier_requests DROP carte_grise_path');
    }
}
