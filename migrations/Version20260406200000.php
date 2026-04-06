<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fixes investissement table created without project_id / user_id (older schema).
 */
final class Version20260406200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add project_id and user_id to investissement if missing';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['investissement'])) {
            return;
        }

        $cols = $sm->listTableColumns('investissement');
        if (isset($cols['project_id'])) {
            return;
        }

        $this->addSql('ALTER TABLE investissement ADD project_id INT DEFAULT NULL, ADD user_id BIGINT DEFAULT NULL');
        $this->addSql('DELETE FROM investissement WHERE project_id IS NULL OR user_id IS NULL');
        $this->addSql('ALTER TABLE investissement MODIFY project_id INT NOT NULL, MODIFY user_id BIGINT NOT NULL');
        $this->addSql('CREATE INDEX IDX_B8E64E01166D1F9C ON investissement (project_id)');
        $this->addSql('CREATE INDEX IDX_B8E64E01A76ED395 ON investissement (user_id)');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['investissement'])) {
            return;
        }

        $cols = $sm->listTableColumns('investissement');
        if (!isset($cols['project_id'])) {
            return;
        }

        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01166D1F9C');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01A76ED395');
        $this->addSql('DROP INDEX IDX_B8E64E01166D1F9C ON investissement');
        $this->addSql('DROP INDEX IDX_B8E64E01A76ED395 ON investissement');
        $this->addSql('ALTER TABLE investissement DROP project_id, DROP user_id');
    }
}
