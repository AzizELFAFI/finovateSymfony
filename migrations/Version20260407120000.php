<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map legacy investment status ACTIVE to CONFIRMED for request workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE investissement SET status = 'CONFIRMED' WHERE status = 'ACTIVE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE investissement SET status = 'ACTIVE' WHERE status = 'CONFIRMED'");
    }
}
