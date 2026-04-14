<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414123900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face 2FA fields (enabled + embedding) to user table for InsightFace integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD face_auth_enabled TINYINT(1) NOT NULL, ADD face_embedding LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP face_auth_enabled, DROP face_embedding');
    }
}
