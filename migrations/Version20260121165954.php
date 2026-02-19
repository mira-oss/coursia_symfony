<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121165954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses ADD package_weight VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE courses ADD package_photo_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE journeys ADD max_package_weight VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses DROP package_weight');
        $this->addSql('ALTER TABLE courses DROP package_photo_path');
        $this->addSql('ALTER TABLE journeys DROP max_package_weight');
    }
}
