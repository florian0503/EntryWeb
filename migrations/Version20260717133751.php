<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717133751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la date de relance sur prospect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prospect ADD date_relance DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prospect DROP date_relance');
    }
}
