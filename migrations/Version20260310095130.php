<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260310095130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 1) añadir columna como NULLABLE (temporal)
        $this->addSql('ALTER TABLE product ADD title VARCHAR(255) DEFAULT NULL');

        // 2) rellenar title para los productos ya existentes
        //   prioridad: description -> model -> article
        $this->addSql("
        UPDATE product
        SET title = COALESCE(NULLIF(TRIM(description), ''), NULLIF(TRIM(model), ''), NULLIF(TRIM(article), ''), 'Producto')
        WHERE title IS NULL
    ");

        // 3) poner NOT NULL
        $this->addSql('ALTER TABLE product ALTER COLUMN title SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN title');
    }
}
