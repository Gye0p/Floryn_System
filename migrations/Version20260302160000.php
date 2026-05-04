<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Merge Customer entity into User entity.
 *
 * 1. Add fullName, phone, address columns to user table
 * 2. Ensure existing staff/admin users have ROLE_STAFF explicitly stored
 * 3. Create user records for orphan customers (those without a linked user)
 * 4. Copy profile data from linked customers to their user records
 * 5. Re-point reservation.customer_id and notification_log.customer_id to user table
 * 6. Drop customer table
 */
final class Version20260302160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge Customer entity into User – single user table with ROLE_CUSTOMER';
    }

    public function up(Schema $schema): void
    {
        // 1. Add profile columns to user table
        $this->addSql('ALTER TABLE user ADD full_name VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(255) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL');

        // 2. Copy profile data from linked customers (those with user_id) into their user records
        $this->addSql('UPDATE user u INNER JOIN customer c ON c.user_id = u.id SET u.full_name = c.full_name, u.phone = c.phone, u.address = c.address');

        // 3. Create user records for orphan customers (user_id IS NULL)
        //    They get ROLE_CUSTOMER, a dummy password (cannot log in), is_approved = true
        $this->addSql("
            INSERT INTO user (username, roles, password, email, is_approved, created_at, full_name, phone, address)
            SELECT
                CONCAT('customer_', c.id),
                '[\"ROLE_CUSTOMER\"]',
                '__no_password__',
                c.email,
                1,
                c.date_registered,
                c.full_name,
                c.phone,
                c.address
            FROM customer c
            WHERE c.user_id IS NULL
        ");

        // 4. Drop old FK constraints on reservation and notification_log
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559395C3F3');
        $this->addSql('ALTER TABLE notification_log DROP FOREIGN KEY FK_ED15DF29395C3F3');

        // 5. Update reservation.customer_id: linked customers → their user_id
        $this->addSql('UPDATE reservation r INNER JOIN customer c ON r.customer_id = c.id AND c.user_id IS NOT NULL SET r.customer_id = c.user_id');

        // 6. Update reservation.customer_id: orphan customers → newly created user
        $this->addSql("UPDATE reservation r INNER JOIN customer c ON r.customer_id = c.id AND c.user_id IS NULL INNER JOIN user u ON u.username = CONCAT('customer_', c.id) SET r.customer_id = u.id");

        // 7. Same for notification_log
        $this->addSql('UPDATE notification_log n INNER JOIN customer c ON n.customer_id = c.id AND c.user_id IS NOT NULL SET n.customer_id = c.user_id');
        $this->addSql("UPDATE notification_log n INNER JOIN customer c ON n.customer_id = c.id AND c.user_id IS NULL INNER JOIN user u ON u.username = CONCAT('customer_', c.id) SET n.customer_id = u.id");

        // 8. Add new FK constraints pointing to user table
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF29395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id)');

        // 9. Drop customer table (also drops its FK to user and its own indexes)
        $this->addSql('DROP TABLE customer');
    }

    public function down(Schema $schema): void
    {
        // Re-create customer table
        $this->addSql('CREATE TABLE customer (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            full_name VARCHAR(255) NOT NULL,
            phone VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address VARCHAR(255) DEFAULT NULL,
            date_registered DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_81398E09A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');

        // Drop new FK constraints
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559395C3F3');
        $this->addSql('ALTER TABLE notification_log DROP FOREIGN KEY FK_ED15DF29395C3F3');

        // Re-create customers from ROLE_CUSTOMER users
        $this->addSql("INSERT INTO customer (full_name, phone, email, address, date_registered, user_id)
            SELECT u.full_name, COALESCE(u.phone, ''), u.email, u.address, COALESCE(u.created_at, NOW()), u.id
            FROM user u WHERE u.roles LIKE '%ROLE_CUSTOMER%'");

        // Re-point reservation.customer_id back to customer.id
        $this->addSql('UPDATE reservation r INNER JOIN customer c ON c.user_id = r.customer_id SET r.customer_id = c.id');
        $this->addSql('UPDATE notification_log n INNER JOIN customer c ON c.user_id = n.customer_id SET n.customer_id = c.id');

        // Re-add FK constraints to customer table
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_ED15DF29395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');

        // Remove profile columns from user
        $this->addSql('ALTER TABLE user DROP full_name, DROP phone, DROP address');
    }
}
