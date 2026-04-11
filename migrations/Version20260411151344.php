<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411151344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // Create tables only if they don't exist yet
        if (!$sm->tablesExist(['ad'])) {
            $this->addSql('CREATE TABLE ad (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, image_path VARCHAR(255) NOT NULL, duration INT NOT NULL, reward_points INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$sm->tablesExist(['product'])) {
            $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price_points INT NOT NULL, image VARCHAR(255) DEFAULT NULL, stock INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        if (!$sm->tablesExist(['user_ad_click'])) {
            $this->addSql('CREATE TABLE user_ad_click (id INT AUTO_INCREMENT NOT NULL, clicked_at DATETIME NOT NULL, user_id BIGINT DEFAULT NULL, ad_id INT DEFAULT NULL, INDEX IDX_671B7CA5A76ED395 (user_id), INDEX IDX_671B7CA54F34D596 (ad_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE user_ad_click ADD CONSTRAINT FK_671B7CA5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE user_ad_click ADD CONSTRAINT FK_671B7CA54F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id)');
        }

        // shared_posts — drop target_forum_id / comment columns if they exist
        $sharedCols = array_keys($sm->listTableColumns('shared_posts'));
        if (in_array('target_forum_id', $sharedCols)) {
            $sharedFks = array_map(fn($fk) => $fk->getName(), $sm->listTableForeignKeys('shared_posts'));
            if (in_array('FK_763357E7F3676AB9', $sharedFks)) {
                $this->addSql('ALTER TABLE shared_posts DROP FOREIGN KEY `FK_763357E7F3676AB9`');
            }
            $sharedIndexes = array_map(fn($i) => $i->getName(), $sm->listTableIndexes('shared_posts'));
            if (in_array('IDX_763357E7F3676AB9', $sharedIndexes)) {
                $this->addSql('DROP INDEX IDX_763357E7F3676AB9 ON shared_posts');
            }
            $dropCols = 'DROP target_forum_id';
            if (in_array('comment', $sharedCols)) $dropCols .= ', DROP comment';
            $this->addSql('ALTER TABLE shared_posts ' . $dropCols);
        }

        // project FK — only add if missing
        $projectFks = array_map(fn($fk) => $fk->getName(), $sm->listTableForeignKeys('project'));
        if (!in_array('FK_2FB3D0EE7E3C61F9', $projectFks)) {
            // Nullify orphan owner_ids before adding FK
            $this->addSql('UPDATE project SET owner_id = NULL WHERE owner_id IS NOT NULL AND owner_id NOT IN (SELECT id FROM user)');
            $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        $projectIndexes = array_map(fn($i) => $i->getName(), $sm->listTableIndexes('project'));
        if (!in_array('IDX_2FB3D0EE7E3C61F9', $projectIndexes)) {
            $this->addSql('CREATE INDEX IDX_2FB3D0EE7E3C61F9 ON project (owner_id)');
        }

        // user_blocks indexes — rename if using old names
        $ubIndexes = array_map(fn($i) => strtolower($i->getName()), $sm->listTableIndexes('user_blocks'));
        if (in_array('idx_blocker', $ubIndexes)) {
            $ubFks = array_map(fn($fk) => strtolower($fk->getName()), $sm->listTableForeignKeys('user_blocks'));
            if (in_array('fk_block_blocker', $ubFks)) {
                $this->addSql('ALTER TABLE user_blocks DROP FOREIGN KEY `FK_block_blocker`');
            }
            $this->addSql('DROP INDEX idx_blocker ON user_blocks');
            $this->addSql('CREATE INDEX IDX_ABBF8E45548D5975 ON user_blocks (blocker_id)');
            $this->addSql('ALTER TABLE user_blocks ADD CONSTRAINT `FK_block_blocker` FOREIGN KEY (blocker_id) REFERENCES user (id) ON DELETE CASCADE');
        }
        if (in_array('idx_blocked', $ubIndexes)) {
            $ubFks = array_map(fn($fk) => strtolower($fk->getName()), $sm->listTableForeignKeys('user_blocks'));
            if (in_array('fk_block_blocked', $ubFks)) {
                $this->addSql('ALTER TABLE user_blocks DROP FOREIGN KEY `FK_block_blocked`');
            }
            $this->addSql('DROP INDEX idx_blocked ON user_blocks');
            $this->addSql('CREATE INDEX IDX_ABBF8E4521FF5136 ON user_blocks (blocked_id)');
            $this->addSql('ALTER TABLE user_blocks ADD CONSTRAINT `FK_block_blocked` FOREIGN KEY (blocked_id) REFERENCES user (id) ON DELETE CASCADE');
        }

        // user_peer_restrictions indexes — rename if using old names
        $uprIndexes = array_map(fn($i) => strtolower($i->getName()), $sm->listTableIndexes('user_peer_restrictions'));
        if (in_array('idx_restrictor', $uprIndexes)) {
            $uprFks = array_map(fn($fk) => strtolower($fk->getName()), $sm->listTableForeignKeys('user_peer_restrictions'));
            if (in_array('fk_peer_restrictor', $uprFks)) {
                $this->addSql('ALTER TABLE user_peer_restrictions DROP FOREIGN KEY `FK_peer_restrictor`');
            }
            $this->addSql('DROP INDEX idx_restrictor ON user_peer_restrictions');
            $this->addSql('CREATE INDEX IDX_5E63C5F885075880 ON user_peer_restrictions (restrictor_id)');
            $this->addSql('ALTER TABLE user_peer_restrictions ADD CONSTRAINT `FK_peer_restrictor` FOREIGN KEY (restrictor_id) REFERENCES user (id) ON DELETE CASCADE');
        }
        if (in_array('idx_restricted', $uprIndexes)) {
            $uprFks = array_map(fn($fk) => strtolower($fk->getName()), $sm->listTableForeignKeys('user_peer_restrictions'));
            if (in_array('fk_peer_restricted', $uprFks)) {
                $this->addSql('ALTER TABLE user_peer_restrictions DROP FOREIGN KEY `FK_peer_restricted`');
            }
            $this->addSql('DROP INDEX idx_restricted ON user_peer_restrictions');
            $this->addSql('CREATE INDEX IDX_5E63C5F8BAC54862 ON user_peer_restrictions (restricted_id)');
            $this->addSql('ALTER TABLE user_peer_restrictions ADD CONSTRAINT `FK_peer_restricted` FOREIGN KEY (restricted_id) REFERENCES user (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_ad_click DROP FOREIGN KEY FK_671B7CA5A76ED395');
        $this->addSql('ALTER TABLE user_ad_click DROP FOREIGN KEY FK_671B7CA54F34D596');
        $this->addSql('DROP TABLE ad');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE user_ad_click');
        $this->addSql('ALTER TABLE alerts CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE badge_types CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE comments CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE flagged_content CHANGE detected_at detected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forums CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forum_recommendations CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01166D1F9C');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01A76ED395');
        $this->addSql('ALTER TABLE investissement CHANGE id id INT NOT NULL, CHANGE investment_date investment_date DATETIME DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE posts CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE7E3C61F9');
        $this->addSql('DROP INDEX IDX_2FB3D0EE7E3C61F9 ON project');
        $this->addSql('ALTER TABLE project MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE id id INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE shared_posts ADD target_forum_id INT DEFAULT NULL, ADD comment LONGTEXT DEFAULT NULL, CHANGE shared_at shared_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE shared_posts ADD CONSTRAINT `FK_763357E7F3676AB9` FOREIGN KEY (target_forum_id) REFERENCES forums (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_763357E7F3676AB9 ON shared_posts (target_forum_id)');
        $this->addSql('ALTER TABLE transaction MODIFY id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE role role VARCHAR(50) DEFAULT \'USER\' NOT NULL, CHANGE points points INT DEFAULT 0 NOT NULL, CHANGE solde solde VARCHAR(255) DEFAULT \'500\' NOT NULL, CHANGE cin cin VARCHAR(50) DEFAULT \'\' NOT NULL, CHANGE phone_number phone_number INT DEFAULT 0 NOT NULL, CHANGE numero_carte numero_carte BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497300D14B ON user (numero_carte)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649ABE530DA ON user (cin)');
        $this->addSql('ALTER TABLE user_badges CHANGE earned_at earned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_blocks DROP FOREIGN KEY FK_ABBF8E45548D5975');
        $this->addSql('ALTER TABLE user_blocks DROP FOREIGN KEY FK_ABBF8E4521FF5136');
        $this->addSql('ALTER TABLE user_blocks CHANGE reason reason TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_abbf8e45548d5975 ON user_blocks');
        $this->addSql('CREATE INDEX IDX_blocker ON user_blocks (blocker_id)');
        $this->addSql('DROP INDEX idx_abbf8e4521ff5136 ON user_blocks');
        $this->addSql('CREATE INDEX IDX_blocked ON user_blocks (blocked_id)');
        $this->addSql('ALTER TABLE user_blocks ADD CONSTRAINT FK_ABBF8E45548D5975 FOREIGN KEY (blocker_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_blocks ADD CONSTRAINT FK_ABBF8E4521FF5136 FOREIGN KEY (blocked_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_forum CHANGE joined_at joined_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_interactions CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_peer_restrictions DROP FOREIGN KEY FK_5E63C5F885075880');
        $this->addSql('ALTER TABLE user_peer_restrictions DROP FOREIGN KEY FK_5E63C5F8BAC54862');
        $this->addSql('ALTER TABLE user_peer_restrictions CHANGE reason reason TEXT DEFAULT NULL, CHANGE active active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('DROP INDEX idx_5e63c5f885075880 ON user_peer_restrictions');
        $this->addSql('CREATE INDEX IDX_restrictor ON user_peer_restrictions (restrictor_id)');
        $this->addSql('DROP INDEX idx_5e63c5f8bac54862 ON user_peer_restrictions');
        $this->addSql('CREATE INDEX IDX_restricted ON user_peer_restrictions (restricted_id)');
        $this->addSql('ALTER TABLE user_peer_restrictions ADD CONSTRAINT FK_5E63C5F885075880 FOREIGN KEY (restrictor_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_peer_restrictions ADD CONSTRAINT FK_5E63C5F8BAC54862 FOREIGN KEY (restricted_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_reports CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_restrictions CHANGE restricted_until restricted_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE votes CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
