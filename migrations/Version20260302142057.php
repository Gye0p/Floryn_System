<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302142057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_log DROP FOREIGN KEY FK_F65507A12C09D409');
        $this->addSql('DROP TABLE inventory_log');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE inventory_log (id INT AUTO_INCREMENT NOT NULL, flower_id INT NOT NULL, quantity_in INT NOT NULL, quantity_out INT NOT NULL, date_updated DATETIME NOT NULL, remarks LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_F65507A12C09D409 (flower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE inventory_log ADD CONSTRAINT FK_F65507A12C09D409 FOREIGN KEY (flower_id) REFERENCES flower (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
