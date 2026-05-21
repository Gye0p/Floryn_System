<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_token table for JWT refresh flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_REFRESH_TOKEN_USER (user_id),
            INDEX idx_refresh_token_hash (token_hash),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_REFRESH_TOKEN_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_REFRESH_TOKEN_USER');
        $this->addSql('DROP TABLE refresh_token');
    }
}
