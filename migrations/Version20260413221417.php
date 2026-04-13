<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260413221417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE investissement ADD CONSTRAINT FK_B8E64E01A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE password_reset_request DROP FOREIGN KEY `FK_44B93F3FA76ED395`');
        $this->addSql('DROP INDEX uniq_44b93f3f8f3c7a9b ON password_reset_request');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D0A95AB3BC57DA ON password_reset_request (token_hash)');
        $this->addSql('DROP INDEX idx_44b93f3fa76ed395 ON password_reset_request');
        $this->addSql('CREATE INDEX IDX_C5D0A95AA76ED395 ON password_reset_request (user_id)');
        $this->addSql('ALTER TABLE password_reset_request ADD CONSTRAINT `FK_44B93F3FA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_posts CHANGE shared_at shared_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX UNIQ_8D93D6497300D14B ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649ABE530DA ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT NOT NULL, CHANGE id id BIGINT NOT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE points points INT NOT NULL, CHANGE solde solde VARCHAR(255) NOT NULL, CHANGE numero_carte numero_carte BIGINT NOT NULL, CHANGE cin cin VARCHAR(50) NOT NULL, CHANGE phone_number phone_number INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01166D1F9C');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01A76ED395');
        $this->addSql('ALTER TABLE password_reset_request DROP FOREIGN KEY FK_C5D0A95AA76ED395');
        $this->addSql('DROP INDEX uniq_c5d0a95ab3bc57da ON password_reset_request');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_44B93F3F8F3C7A9B ON password_reset_request (token_hash)');
        $this->addSql('DROP INDEX idx_c5d0a95aa76ed395 ON password_reset_request');
        $this->addSql('CREATE INDEX IDX_44B93F3FA76ED395 ON password_reset_request (user_id)');
        $this->addSql('ALTER TABLE password_reset_request ADD CONSTRAINT FK_C5D0A95AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_posts CHANGE shared_at shared_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user DROP is_verified, CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE role role VARCHAR(50) DEFAULT \'USER\' NOT NULL, CHANGE points points INT DEFAULT 0 NOT NULL, CHANGE solde solde VARCHAR(255) DEFAULT \'500\' NOT NULL, CHANGE cin cin VARCHAR(50) DEFAULT \'\' NOT NULL, CHANGE phone_number phone_number INT DEFAULT 0 NOT NULL, CHANGE numero_carte numero_carte BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497300D14B ON user (numero_carte)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649ABE530DA ON user (cin)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
    }
}
