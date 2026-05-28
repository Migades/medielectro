<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade columna images (JSON) a product para galería de imágenes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD images JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN images');
    }
}
