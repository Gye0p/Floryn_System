<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isApproved and createdAt fields to user table for account approval workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD is_approved TINYINT(1) NOT NULL DEFAULT 0");
        $this->addSql("ALTER TABLE `user` ADD created_at DATETIME DEFAULT NULL");
        // Approve all existing users so they are not locked out
        $this->addSql("UPDATE `user` SET is_approved = 1, created_at = NOW() WHERE is_approved = 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` DROP COLUMN is_approved");
        $this->addSql("ALTER TABLE `user` DROP COLUMN created_at");
    }
}
