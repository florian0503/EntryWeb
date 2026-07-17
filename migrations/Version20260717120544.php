<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717120544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les coordonnees GPS (latitude/longitude) sur prospect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prospect ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prospect DROP latitude, DROP longitude');
    }
}
