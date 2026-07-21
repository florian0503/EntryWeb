<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721144044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les screenshots tablette et mobile aux realisations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE realisation ADD image_tablet_name VARCHAR(255) DEFAULT NULL, ADD image_mobile_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE realisation DROP image_tablet_name, DROP image_mobile_name');
    }
}
