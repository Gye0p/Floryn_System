<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_filename column to flower table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flower ADD image_filename VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flower DROP image_filename');
    }
}
