<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project.owner_id for project ownership';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD owner_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE7E3C61F9 ON project (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE7E3C61F9');
        $this->addSql('DROP INDEX IDX_2FB3D0EE7E3C61F9 ON project');
        $this->addSql('ALTER TABLE project DROP owner_id');
    }
}
