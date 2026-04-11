<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411153452 extends AbstractMigration
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
        $this->addSql('ALTER TABLE project CHANGE id id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE shared_posts CHANGE shared_at shared_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('DROP INDEX UNIQ_8D93D6497300D14B ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649ABE530DA ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT NOT NULL, CHANGE role role VARCHAR(50) NOT NULL, CHANGE points points INT NOT NULL, CHANGE solde solde VARCHAR(255) NOT NULL, CHANGE numero_carte numero_carte BIGINT NOT NULL, CHANGE cin cin VARCHAR(50) NOT NULL, CHANGE phone_number phone_number INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01166D1F9C');
        $this->addSql('ALTER TABLE investissement DROP FOREIGN KEY FK_B8E64E01A76ED395');
        $this->addSql('ALTER TABLE project MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE id id INT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE shared_posts CHANGE shared_at shared_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE transaction MODIFY id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transaction CHANGE id id BIGINT NOT NULL, DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE role role VARCHAR(50) DEFAULT \'USER\' NOT NULL, CHANGE points points INT DEFAULT 0 NOT NULL, CHANGE solde solde VARCHAR(255) DEFAULT \'500\' NOT NULL, CHANGE cin cin VARCHAR(50) DEFAULT \'\' NOT NULL, CHANGE phone_number phone_number INT DEFAULT 0 NOT NULL, CHANGE numero_carte numero_carte BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497300D14B ON user (numero_carte)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649ABE530DA ON user (cin)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
    }
}
