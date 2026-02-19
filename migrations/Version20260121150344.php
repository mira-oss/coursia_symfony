<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121150344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses ADD delivery_date_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE courses RENAME COLUMN scheduled_at TO delivery_date_start');
        $this->addSql('ALTER TABLE "user" ADD is_verified BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD id_card_number VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD id_card_photo_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD selfie_with_id_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD verified_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D64969F4B775 FOREIGN KEY (verified_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8D93D64969F4B775 ON "user" (verified_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses ADD scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE courses DROP delivery_date_start');
        $this->addSql('ALTER TABLE courses DROP delivery_date_end');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D64969F4B775');
        $this->addSql('DROP INDEX IDX_8D93D64969F4B775');
        $this->addSql('ALTER TABLE "user" DROP is_verified');
        $this->addSql('ALTER TABLE "user" DROP id_card_number');
        $this->addSql('ALTER TABLE "user" DROP id_card_photo_path');
        $this->addSql('ALTER TABLE "user" DROP selfie_with_id_path');
        $this->addSql('ALTER TABLE "user" DROP verified_at');
        $this->addSql('ALTER TABLE "user" DROP verified_by_id');
    }
}
