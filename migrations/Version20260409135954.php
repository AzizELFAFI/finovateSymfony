<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409135954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ad (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, image_path VARCHAR(255) NOT NULL, duration INT NOT NULL, reward_points INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price_points INT NOT NULL, image VARCHAR(255) DEFAULT NULL, stock INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_ad_click (id INT AUTO_INCREMENT NOT NULL, clicked_at DATETIME NOT NULL, user_id BIGINT DEFAULT NULL, ad_id INT DEFAULT NULL, INDEX IDX_671B7CA5A76ED395 (user_id), INDEX IDX_671B7CA54F34D596 (ad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_ad_click ADD CONSTRAINT FK_671B7CA5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_ad_click ADD CONSTRAINT FK_671B7CA54F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id)');
        $this->addSql('ALTER TABLE investissement CHANGE investment_date investment_date DATETIME NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_ad_click DROP FOREIGN KEY FK_671B7CA5A76ED395');
        $this->addSql('ALTER TABLE user_ad_click DROP FOREIGN KEY FK_671B7CA54F34D596');
        $this->addSql('DROP TABLE ad');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE user_ad_click');
        $this->addSql('ALTER TABLE investissement CHANGE investment_date investment_date DATETIME DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');
    }
}
