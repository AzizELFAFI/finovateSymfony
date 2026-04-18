<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418030705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ad ADD target_age_min INT DEFAULT NULL, ADD target_age_max INT DEFAULT NULL, ADD target_gender VARCHAR(255) DEFAULT NULL, ADD target_interests VARCHAR(1000) DEFAULT NULL, ADD category VARCHAR(100) DEFAULT NULL, ADD priority DOUBLE PRECISION DEFAULT NULL, ADD is_active TINYINT NOT NULL');
        $this->addSql('ALTER TABLE goal CHANGE id id INT NOT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id INT NOT NULL, CHANGE receiver_id receiver_id INT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ad DROP target_age_min, DROP target_age_max, DROP target_gender, DROP target_interests, DROP category, DROP priority, DROP is_active');
        $this->addSql('ALTER TABLE goal CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE id_user id_user BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id BIGINT DEFAULT NULL, CHANGE receiver_id receiver_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT AUTO_INCREMENT NOT NULL');
    }
}
