<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122150129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses ADD departure_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE courses ADD departure_longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE courses ADD delivery_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE courses ADD delivery_longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE journeys ADD departure_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE journeys ADD departure_longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE journeys ADD delivery_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE journeys ADD delivery_longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses DROP departure_latitude');
        $this->addSql('ALTER TABLE courses DROP departure_longitude');
        $this->addSql('ALTER TABLE courses DROP delivery_latitude');
        $this->addSql('ALTER TABLE courses DROP delivery_longitude');
        $this->addSql('ALTER TABLE journeys DROP departure_latitude');
        $this->addSql('ALTER TABLE journeys DROP departure_longitude');
        $this->addSql('ALTER TABLE journeys DROP delivery_latitude');
        $this->addSql('ALTER TABLE journeys DROP delivery_longitude');
    }
}
