<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Elimina la tabla category, que era una entidad huérfana sin relaciones
 * ni uso en ningún controller o servicio.
 */
final class Version20260417000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphan category table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            is_active BOOLEAN NOT NULL
        )');
    }
}
