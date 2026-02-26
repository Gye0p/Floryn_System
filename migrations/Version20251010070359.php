<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010070359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, date_registered DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flower (id INT AUTO_INCREMENT NOT NULL, supplier_id INT NOT NULL, name VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, stock_quantity INT NOT NULL, freshness_status VARCHAR(50) NOT NULL, date_received DATE NOT NULL, expiry_date DATE NOT NULL, status VARCHAR(50) NOT NULL, INDEX IDX_A7D7C1DA2ADD6D8C (supplier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventory_log (id INT AUTO_INCREMENT NOT NULL, flower_id INT NOT NULL, quantity_in INT NOT NULL, quantity_out INT NOT NULL, date_updated DATETIME NOT NULL, remarks LONGTEXT NOT NULL, INDEX IDX_F65507A12C09D409 (flower_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_log (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, reservation_id INT DEFAULT NULL, message LONGTEXT NOT NULL, channel VARCHAR(50) NOT NULL, date_sent DATETIME NOT NULL, status VARCHAR(255) NOT NULL, INDEX IDX_ED15DF29395C3F3 (customer_id), INDEX IDX_ED15DF2B83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, reservation_id INT NOT NULL, payment_date DATETIME NOT NULL, amount_paid DOUBLE PRECISION NOT NULL, payment_method VARCHAR(255) NOT NULL, reference_no VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_6D28840DB83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, pickup_date DATE NOT NULL, total_amount DOUBLE PRECISION NOT NULL, payment_status VARCHAR(255) NOT NULL, reservation_status VARCHAR(255) NOT NULL, date_reserved DATETIME NOT NULL, INDEX IDX_42C849559395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation_detail (id INT AUTO_INCREMENT NOT NULL, flower_id INT NOT NULL, reservation_id INT NOT NULL, quantity INT NOT NULL, subtotal DOUBLE PRECISION NOT NULL, INDEX IDX_66F736082C09D409 (flower_id), INDEX IDX_66F73608B83297E7 (reservation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, supplier_name VARCHAR(255) NOT NULL, contact_person VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, email VARCHAR(255) NOT NULL, address LONGTEXT NOT NULL, delivery_schedule VARCHAR(255) NOT NULL, date_added DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE flower ADD CONSTRAINT FK_A7D7C1DA2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('ALTER TABLE inventory_log ADD CONSTRAINT FK_F65507A12C09D409 FOREIGN KEY (flower_id) REFERENCES flower (id)');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF29395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF2B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE reservation_detail ADD CONSTRAINT FK_66F736082C09D409 FOREIGN KEY (flower_id) REFERENCES flower (id)');
        $this->addSql('ALTER TABLE reservation_detail ADD CONSTRAINT FK_66F73608B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flower DROP FOREIGN KEY FK_A7D7C1DA2ADD6D8C');
        $this->addSql('ALTER TABLE inventory_log DROP FOREIGN KEY FK_F65507A12C09D409');
        $this->addSql('ALTER TABLE notification_log DROP FOREIGN KEY FK_ED15DF29395C3F3');
        $this->addSql('ALTER TABLE notification_log DROP FOREIGN KEY FK_ED15DF2B83297E7');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DB83297E7');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559395C3F3');
        $this->addSql('ALTER TABLE reservation_detail DROP FOREIGN KEY FK_66F736082C09D409');
        $this->addSql('ALTER TABLE reservation_detail DROP FOREIGN KEY FK_66F73608B83297E7');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE flower');
        $this->addSql('DROP TABLE inventory_log');
        $this->addSql('DROP TABLE notification_log');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE reservation_detail');
        $this->addSql('DROP TABLE supplier');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
