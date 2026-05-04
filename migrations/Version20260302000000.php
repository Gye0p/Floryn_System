<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add bouquet and bouquet_item tables.
 * Add password and roles columns to customer for customer portal authentication.
 */
final class Version20260302000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Bouquet/BouquetItem entities and customer authentication fields';
    }

    public function up(Schema $schema): void
    {
        // ── Bouquet ──────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE bouquet (
                id          INT AUTO_INCREMENT NOT NULL,
                name        VARCHAR(255)     NOT NULL,
                description LONGTEXT         DEFAULT NULL,
                notes       LONGTEXT         DEFAULT NULL,
                status      VARCHAR(50)      NOT NULL DEFAULT 'Draft',
                total_price DOUBLE PRECISION NOT NULL DEFAULT 0,
                created_at  DATETIME         NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // ── BouquetItem ───────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE bouquet_item (
                id         INT AUTO_INCREMENT NOT NULL,
                bouquet_id INT              NOT NULL,
                flower_id  INT              NOT NULL,
                quantity   INT              NOT NULL,
                unit_price DOUBLE PRECISION NOT NULL,
                subtotal   DOUBLE PRECISION NOT NULL,
                INDEX IDX_BOUQUET_ITEM_BOUQUET (bouquet_id),
                INDEX IDX_BOUQUET_ITEM_FLOWER  (flower_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE bouquet_item ADD CONSTRAINT FK_BOUQUET_ITEM_BOUQUET FOREIGN KEY (bouquet_id) REFERENCES bouquet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bouquet_item ADD CONSTRAINT FK_BOUQUET_ITEM_FLOWER  FOREIGN KEY (flower_id)  REFERENCES flower  (id)');

        // ── Customer authentication ──────────────────────────────────────────
        // password is nullable: null means the customer record exists but has no portal account yet.
        // roles is a JSON array, defaulting to an empty array.
        $this->addSql("ALTER TABLE customer ADD password VARCHAR(255) DEFAULT NULL, ADD roles JSON NOT NULL DEFAULT ('[]')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bouquet_item DROP FOREIGN KEY FK_BOUQUET_ITEM_BOUQUET');
        $this->addSql('ALTER TABLE bouquet_item DROP FOREIGN KEY FK_BOUQUET_ITEM_FLOWER');
        $this->addSql('DROP TABLE bouquet_item');
        $this->addSql('DROP TABLE bouquet');
        $this->addSql('ALTER TABLE customer DROP password, DROP roles');
    }
}
