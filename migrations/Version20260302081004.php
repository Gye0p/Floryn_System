<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302081004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bouquet CHANGE status status VARCHAR(50) NOT NULL, CHANGE total_price total_price DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE bouquet_item DROP FOREIGN KEY FK_BOUQUET_ITEM_BOUQUET');
        $this->addSql('ALTER TABLE bouquet_item ADD CONSTRAINT FK_A2B9DE356C8DF983 FOREIGN KEY (bouquet_id) REFERENCES bouquet (id)');
        $this->addSql('ALTER TABLE bouquet_item RENAME INDEX idx_bouquet_item_bouquet TO IDX_A2B9DE356C8DF983');
        $this->addSql('ALTER TABLE bouquet_item RENAME INDEX idx_bouquet_item_flower TO IDX_A2B9DE352C09D409');
        $this->addSql('ALTER TABLE customer DROP password, DROP roles');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bouquet CHANGE status status VARCHAR(50) DEFAULT \'Draft\' NOT NULL, CHANGE total_price total_price DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE bouquet_item DROP FOREIGN KEY FK_A2B9DE356C8DF983');
        $this->addSql('ALTER TABLE bouquet_item ADD CONSTRAINT FK_BOUQUET_ITEM_BOUQUET FOREIGN KEY (bouquet_id) REFERENCES bouquet (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bouquet_item RENAME INDEX idx_a2b9de352c09d409 TO IDX_BOUQUET_ITEM_FLOWER');
        $this->addSql('ALTER TABLE bouquet_item RENAME INDEX idx_a2b9de356c8df983 TO IDX_BOUQUET_ITEM_BOUQUET');
        $this->addSql('ALTER TABLE customer ADD password VARCHAR(255) DEFAULT NULL, ADD roles JSON DEFAULT \'_utf8mb4\\\\\'\'[]\\\\\'\'\' NOT NULL');
    }
}
