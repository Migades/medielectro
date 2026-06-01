<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade columna attributes (JSON) a product para filtros dinámicos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD attributes JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP COLUMN attributes');
    }
}
