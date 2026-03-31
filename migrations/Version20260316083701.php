<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316083701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chevalier_requests ADD request_type VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD cip_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD passport_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD passport_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD visa_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD billet_avion_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD destination_country VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE chevalier_requests ADD destination_address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chevalier_requests DROP request_type');
        $this->addSql('ALTER TABLE chevalier_requests DROP cip_path');
        $this->addSql('ALTER TABLE chevalier_requests DROP passport_number');
        $this->addSql('ALTER TABLE chevalier_requests DROP passport_path');
        $this->addSql('ALTER TABLE chevalier_requests DROP visa_path');
        $this->addSql('ALTER TABLE chevalier_requests DROP billet_avion_path');
        $this->addSql('ALTER TABLE chevalier_requests DROP destination_country');
        $this->addSql('ALTER TABLE chevalier_requests DROP destination_address');
    }
}
