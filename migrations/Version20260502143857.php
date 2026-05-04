<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502143857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comments CHANGE post_id post_id INT NOT NULL');
        $this->addSql('ALTER TABLE investor_revenue_log CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE posts CHANGE forum_id forum_id INT NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE funding_completed_at funding_completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE project_amount_history CHANGE recorded_at recorded_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE project_revenue_share CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE ticket CHANGE statut statut VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id INT NOT NULL, CHANGE receiver_id receiver_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_forum CHANGE forum_id forum_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comments CHANGE post_id post_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE investor_revenue_log CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE posts CHANGE forum_id forum_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project CHANGE funding_completed_at funding_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE project_amount_history CHANGE recorded_at recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE project_revenue_share CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ticket CHANGE statut statut VARCHAR(255) DEFAULT \'NOUVEAU\'');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id BIGINT DEFAULT NULL, CHANGE receiver_id receiver_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_forum CHANGE forum_id forum_id INT DEFAULT NULL');
    }
}
