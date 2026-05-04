<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502141742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE goal CHANGE id id INT NOT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY `FK_INV_LOG_DAILY`');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY `FK_INV_LOG_USER`');
        $this->addSql('ALTER TABLE investor_revenue_log CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_a1b2c3d4e5f60718 ON investor_revenue_log');
        $this->addSql('CREATE INDEX IDX_EBD5DB9ADFA4E588 ON investor_revenue_log (daily_revenue_id)');
        $this->addSql('DROP INDEX idx_a1b2c3d4a76ed395 ON investor_revenue_log');
        $this->addSql('CREATE INDEX IDX_EBD5DB9AA76ED395 ON investor_revenue_log (user_id)');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT `FK_INV_LOG_DAILY` FOREIGN KEY (daily_revenue_id) REFERENCES daily_revenue (revenue_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT `FK_INV_LOG_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_message_ticket`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_message_ticket`');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF4B10539 FOREIGN KEY (idTicket) REFERENCES ticket (id)');
        $this->addSql('DROP INDEX idx_msg_ticket ON message');
        $this->addSql('CREATE INDEX IDX_B6BD307FF4B10539 ON message (idTicket)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_message_ticket` FOREIGN KEY (idTicket) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE posts CHANGE forum_id forum_id INT NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE funding_completed_at funding_completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE project_amount_history DROP FOREIGN KEY `FK_ADDF295166D1F9C`');
        $this->addSql('ALTER TABLE project_amount_history CHANGE recorded_at recorded_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_addf295166d1f9c ON project_amount_history');
        $this->addSql('CREATE INDEX IDX_E1037C97166D1F9C ON project_amount_history (project_id)');
        $this->addSql('ALTER TABLE project_amount_history ADD CONSTRAINT `FK_ADDF295166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY `FK_D7E8E7166D1F9C`');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY `FK_D7E8E7A76ED395`');
        $this->addSql('ALTER TABLE project_revenue_share CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_d7e8e7166d1f9c ON project_revenue_share');
        $this->addSql('CREATE INDEX IDX_ED862232166D1F9C ON project_revenue_share (project_id)');
        $this->addSql('DROP INDEX idx_d7e8e7a76ed395 ON project_revenue_share');
        $this->addSql('CREATE INDEX IDX_ED862232A76ED395 ON project_revenue_share (user_id)');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT `FK_D7E8E7166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT `FK_D7E8E7A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_user`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY `fk_ticket_user`');
        $this->addSql('ALTER TABLE ticket CHANGE statut statut VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP INDEX idx_ticket_user ON ticket');
        $this->addSql('CREATE INDEX IDX_97A0ADA3A76ED395 ON ticket (user_id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id INT NOT NULL, CHANGE receiver_id receiver_id INT NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE user_forum CHANGE forum_id forum_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE goal CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE id_user id_user BIGINT NOT NULL');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY FK_EBD5DB9ADFA4E588');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY FK_EBD5DB9AA76ED395');
        $this->addSql('ALTER TABLE investor_revenue_log CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_ebd5db9adfa4e588 ON investor_revenue_log');
        $this->addSql('CREATE INDEX IDX_A1B2C3D4E5F60718 ON investor_revenue_log (daily_revenue_id)');
        $this->addSql('DROP INDEX idx_ebd5db9aa76ed395 ON investor_revenue_log');
        $this->addSql('CREATE INDEX IDX_A1B2C3D4A76ED395 ON investor_revenue_log (user_id)');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT FK_EBD5DB9ADFA4E588 FOREIGN KEY (daily_revenue_id) REFERENCES daily_revenue (revenue_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT FK_EBD5DB9AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF4B10539');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF4B10539');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_message_ticket` FOREIGN KEY (idTicket) REFERENCES ticket (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_b6bd307ff4b10539 ON message');
        $this->addSql('CREATE INDEX idx_msg_ticket ON message (idTicket)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF4B10539 FOREIGN KEY (idTicket) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE posts CHANGE forum_id forum_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project CHANGE funding_completed_at funding_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE project_amount_history DROP FOREIGN KEY FK_E1037C97166D1F9C');
        $this->addSql('ALTER TABLE project_amount_history CHANGE recorded_at recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_e1037c97166d1f9c ON project_amount_history');
        $this->addSql('CREATE INDEX IDX_ADDF295166D1F9C ON project_amount_history (project_id)');
        $this->addSql('ALTER TABLE project_amount_history ADD CONSTRAINT FK_E1037C97166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY FK_ED862232166D1F9C');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY FK_ED862232A76ED395');
        $this->addSql('ALTER TABLE project_revenue_share CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_ed862232166d1f9c ON project_revenue_share');
        $this->addSql('CREATE INDEX IDX_D7E8E7166D1F9C ON project_revenue_share (project_id)');
        $this->addSql('DROP INDEX idx_ed862232a76ed395 ON project_revenue_share');
        $this->addSql('CREATE INDEX IDX_D7E8E7A76ED395 ON project_revenue_share (user_id)');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_ED862232166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_ED862232A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A76ED395');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A76ED395');
        $this->addSql('ALTER TABLE ticket CHANGE statut statut VARCHAR(255) DEFAULT \'NOUVEAU\'');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_97a0ada3a76ed395 ON ticket');
        $this->addSql('CREATE INDEX idx_ticket_user ON ticket (user_id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction CHANGE sender_id sender_id BIGINT DEFAULT NULL, CHANGE receiver_id receiver_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BIGINT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE user_forum CHANGE forum_id forum_id INT DEFAULT NULL');
    }
}
