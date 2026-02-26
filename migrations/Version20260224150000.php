<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add google_id and email columns to user table for Google OAuth support
 */
final class Version20260224150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_id and email columns to user table for Google OAuth';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD google_id VARCHAR(255) DEFAULT NULL, ADD email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP google_id, DROP email');
    }
}
