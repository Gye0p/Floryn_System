<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226160045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE flower_batch (id INT AUTO_INCREMENT NOT NULL, flower_id INT NOT NULL, quantity_received INT NOT NULL, quantity_remaining INT NOT NULL, date_received DATE NOT NULL, expiry_date DATE NOT NULL, freshness_status VARCHAR(50) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_72578AAF2C09D409 (flower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE flower_batch ADD CONSTRAINT FK_72578AAF2C09D409 FOREIGN KEY (flower_id) REFERENCES flower (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flower_batch DROP FOREIGN KEY FK_72578AAF2C09D409');
        $this->addSql('DROP TABLE flower_batch');
    }
}
