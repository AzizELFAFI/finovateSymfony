<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404015741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_recommendations DROP FOREIGN KEY `FK_25C86C42A76ED395`');
        $this->addSql('DROP INDEX fk_25c86c42a76ed395 ON forum_recommendations');
        $this->addSql('CREATE INDEX IDX_25C86C42A76ED395 ON forum_recommendations (user_id)');
        $this->addSql('ALTER TABLE forum_recommendations ADD CONSTRAINT `FK_25C86C42A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('ALTER TABLE messenger_messages CHANGE id id BIGINT NOT NULL, CHANGE delivered_at delivered_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_interactions DROP FOREIGN KEY `FK_D34708BFA76ED395`');
        $this->addSql('DROP INDEX fk_d34708bfa76ed395 ON user_interactions');
        $this->addSql('CREATE INDEX IDX_D34708BFA76ED395 ON user_interactions (user_id)');
        $this->addSql('ALTER TABLE user_interactions ADD CONSTRAINT `FK_D34708BFA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forum_recommendations DROP FOREIGN KEY FK_25C86C42A76ED395');
        $this->addSql('DROP INDEX idx_25c86c42a76ed395 ON forum_recommendations');
        $this->addSql('CREATE INDEX FK_25C86C42A76ED395 ON forum_recommendations (user_id)');
        $this->addSql('ALTER TABLE forum_recommendations ADD CONSTRAINT FK_25C86C42A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messenger_messages CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE user_interactions DROP FOREIGN KEY FK_D34708BFA76ED395');
        $this->addSql('DROP INDEX idx_d34708bfa76ed395 ON user_interactions');
        $this->addSql('CREATE INDEX FK_D34708BFA76ED395 ON user_interactions (user_id)');
        $this->addSql('ALTER TABLE user_interactions ADD CONSTRAINT FK_D34708BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
