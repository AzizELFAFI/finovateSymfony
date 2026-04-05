<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403131029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add flagged_content and user_restrictions tables for admin dashboard';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE flagged_content (
            id INT AUTO_INCREMENT NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            content_id INT NOT NULL,
            severity VARCHAR(20) NOT NULL,
            flag_type VARCHAR(50) NOT NULL,
            verdict LONGTEXT NOT NULL,
            issues JSON NOT NULL,
            reviewed TINYINT(1) NOT NULL,
            ignored TINYINT(1) NOT NULL,
            detected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user_restrictions (
            id INT AUTO_INCREMENT NOT NULL,
            user_id BIGINT NOT NULL,
            can_post TINYINT(1) NOT NULL,
            can_comment TINYINT(1) NOT NULL,
            can_create_forum TINYINT(1) NOT NULL,
            restricted_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            reason LONGTEXT NOT NULL,
            offense_number INT NOT NULL,
            active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_E764C525A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE user_restrictions ADD CONSTRAINT FK_E764C525A76ED395
            FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_restrictions DROP FOREIGN KEY FK_E764C525A76ED395');
        $this->addSql('DROP TABLE user_restrictions');
        $this->addSql('DROP TABLE flagged_content');
    }
}
