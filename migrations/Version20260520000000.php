<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schema completo Medielectro — MariaDB 10.11
 * Reemplaza todas las migraciones PostgreSQL anteriores.
 */
final class Version20260520000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema completo Medielectro para MariaDB (migración desde PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE family (
            id INT NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE subfamily (
            id INT NOT NULL AUTO_INCREMENT,
            family_id INT NOT NULL,
            code VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL,
            PRIMARY KEY (id),
            INDEX IDX_C39AA97EC35E566A (family_id),
            CONSTRAINT FK_C39AA97EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE product (
            id INT NOT NULL AUTO_INCREMENT,
            family_id INT NOT NULL,
            subfamily_id INT NOT NULL,
            article VARCHAR(100) NOT NULL,
            model VARCHAR(255) NOT NULL,
            ean VARCHAR(50) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            brand VARCHAR(255) DEFAULT NULL,
            price NUMERIC(10, 2) NOT NULL,
            stock INT NOT NULL,
            pvpr NUMERIC(10, 3) DEFAULT NULL,
            iva_tecno VARCHAR(10) DEFAULT NULL,
            obsolete TINYINT(1) NOT NULL,
            digital_canon NUMERIC(10, 2) DEFAULT NULL,
            slug VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            price_without_vat NUMERIC(10, 2) DEFAULT NULL,
            vat_code VARCHAR(5) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX IDX_D34A04ADC35E566A (family_id),
            INDEX IDX_D34A04AD5731FD53 (subfamily_id),
            CONSTRAINT FK_D34A04ADC35E566A FOREIGN KEY (family_id) REFERENCES family (id),
            CONSTRAINT FK_D34A04AD5731FD53 FOREIGN KEY (subfamily_id) REFERENCES subfamily (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE csv_import (
            id INT NOT NULL AUTO_INCREMENT,
            filename VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            total_rows INT NOT NULL,
            imported_rows INT NOT NULL,
            error_rows INT NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME DEFAULT NULL,
            message LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE customer (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(180) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            company VARCHAR(255) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            zip VARCHAR(10) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE `order` (
            id INT NOT NULL AUTO_INCREMENT,
            customer_id INT NOT NULL,
            reference VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL,
            total NUMERIC(10, 2) NOT NULL,
            shipping_address VARCHAR(255) DEFAULT NULL,
            shipping_zip VARCHAR(10) DEFAULT NULL,
            shipping_city VARCHAR(100) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            erp_status VARCHAR(20) NOT NULL,
            erp_response LONGTEXT DEFAULT NULL,
            erp_sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX UNIQ_F5299398AEA34913 (reference),
            INDEX IDX_F52993989395C3F3 (customer_id),
            CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE order_line (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            product_article VARCHAR(100) NOT NULL,
            product_title VARCHAR(255) NOT NULL,
            product_brand VARCHAR(255) DEFAULT NULL,
            unit_price NUMERIC(10, 2) NOT NULL,
            quantity INT NOT NULL,
            subtotal NUMERIC(10, 2) NOT NULL,
            PRIMARY KEY (id),
            INDEX IDX_9CE58EE18D9F6D38 (order_id),
            CONSTRAINT FK_9CE58EE18D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql('CREATE TABLE messenger_messages (
            id BIGINT NOT NULL AUTO_INCREMENT,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX IDX_75EA56E0FB7336F0 (queue_name),
            INDEX IDX_75EA56E0E3BD61CE (available_at),
            INDEX IDX_75EA56E016BA31DB (delivered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_9CE58EE18D9F6D38');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADC35E566A');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD5731FD53');
        $this->addSql('ALTER TABLE subfamily DROP FOREIGN KEY FK_C39AA97EC35E566A');
        $this->addSql('DROP TABLE order_line');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE csv_import');
        $this->addSql('DROP TABLE subfamily');
        $this->addSql('DROP TABLE family');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
