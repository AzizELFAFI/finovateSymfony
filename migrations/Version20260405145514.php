<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405145514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, goal_amount NUMERIC(12, 2) NOT NULL, current_amount NUMERIC(12, 2) DEFAULT NULL, created_at DATETIME DEFAULT NULL, deadline DATE DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, category VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE investissement (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(12, 2) NOT NULL, investment_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, revenue_percentage DOUBLE PRECISION DEFAULT NULL, project_id INT NOT NULL, user_id BIGINT NOT NULL, INDEX IDX_B8E64E01166D1F9C (project_id), INDEX IDX_B8E64E01A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01166D1F9C');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01A76ED395');
        $this->addSql('DROP TABLE investissement');
        $this->addSql('DROP TABLE project');
    }
}
