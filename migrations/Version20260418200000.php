<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record when project funding goal is first reached (daily revenue period starts)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD funding_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP funding_completed_at');
    }
}
