<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404015741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: schema already in desired state from previous migration';
    }

    public function up(Schema $schema): void
    {
        // The DB already has IDX_* indexes and FK constraints in place.
        // This migration is intentionally empty to avoid duplicate key errors.
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
