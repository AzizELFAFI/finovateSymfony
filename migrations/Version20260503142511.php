<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503142511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY IF EXISTS `FK_B6BD307FF4B10539`');
        $this->addSql('DROP INDEX IF EXISTS idx_msg_ticket ON message');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_B6BD307FF4B10539 ON message (idTicket)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `FK_B6BD307FF4B10539` FOREIGN KEY (idTicket) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE project_amount_history DROP FOREIGN KEY IF EXISTS `FK_ADDF295166D1F9C`');
        $this->addSql('DROP INDEX IF EXISTS idx_addf295166d1f9c ON project_amount_history');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_E1037C97166D1F9C ON project_amount_history (project_id)');
        $this->addSql('ALTER TABLE project_amount_history ADD CONSTRAINT `FK_ADDF295166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY IF EXISTS `FK_D7E8E7166D1F9C`');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY IF EXISTS `FK_D7E8E7A76ED395`');
        $this->addSql('DROP INDEX IF EXISTS idx_d7e8e7166d1f9c ON project_revenue_share');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ED862232166D1F9C ON project_revenue_share (project_id)');
        $this->addSql('DROP INDEX IF EXISTS idx_d7e8e7a76ed395 ON project_revenue_share');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ED862232A76ED395 ON project_revenue_share (user_id)');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT `FK_D7E8E7166D1F9C` FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT `FK_D7E8E7A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY IF EXISTS `fk_ticket_user`');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY IF EXISTS FK_97A0ADA3A76ED395');
        $this->addSql('DROP INDEX IF EXISTS idx_ticket_user ON ticket');
        $this->addSql('DROP INDEX IF EXISTS idx_97a0ada3a76ed395 ON ticket');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_97A0ADA3A76ED395 ON ticket (user_id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE votes CHANGE post_id post_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY IF EXISTS FK_B6BD307FF4B10539');
        $this->addSql('DROP INDEX IF EXISTS idx_b6bd307ff4b10539 ON message');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_msg_ticket ON message (idTicket)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF4B10539 FOREIGN KEY (idTicket) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE project_amount_history DROP FOREIGN KEY IF EXISTS FK_E1037C97166D1F9C');
        $this->addSql('DROP INDEX IF EXISTS idx_e1037c97166d1f9c ON project_amount_history');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_ADDF295166D1F9C ON project_amount_history (project_id)');
        $this->addSql('ALTER TABLE project_amount_history ADD CONSTRAINT FK_E1037C97166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY IF EXISTS FK_ED862232166D1F9C');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY IF EXISTS FK_ED862232A76ED395');
        $this->addSql('DROP INDEX IF EXISTS idx_ed862232166d1f9c ON project_revenue_share');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D7E8E7166D1F9C ON project_revenue_share (project_id)');
        $this->addSql('DROP INDEX IF EXISTS idx_ed862232a76ed395 ON project_revenue_share');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_D7E8E7A76ED395 ON project_revenue_share (user_id)');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_ED862232166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_ED862232A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY IF EXISTS FK_97A0ADA3A76ED395');
        $this->addSql('DROP INDEX IF EXISTS idx_97a0ada3a76ed395 ON ticket');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ticket_user ON ticket (user_id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE votes CHANGE post_id post_id INT DEFAULT NULL');
    }
}
