<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Daily revenue, funding snapshots, investor revenue shares and logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE daily_revenue (
            revenue_id INT AUTO_INCREMENT NOT NULL,
            project_id INT NOT NULL,
            revenue_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            amount DOUBLE PRECISION NOT NULL,
            INDEX IDX_A29AC615166D1F9C (project_id),
            UNIQUE INDEX uniq_daily_revenue_project_day (project_id, revenue_date),
            PRIMARY KEY(revenue_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE project_amount_history (
            id BIGINT AUTO_INCREMENT NOT NULL,
            project_id INT NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ADDF295166D1F9C (project_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE project_revenue_share (
            id BIGINT AUTO_INCREMENT NOT NULL,
            project_id INT NOT NULL,
            investissement_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            percentage NUMERIC(8, 4) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_project_revenue_share_investissement (investissement_id),
            INDEX IDX_D7E8E7166D1F9C (project_id),
            INDEX IDX_D7E8E7A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE investor_revenue_log (
            id BIGINT AUTO_INCREMENT NOT NULL,
            project_revenue_share_id BIGINT NOT NULL,
            daily_revenue_id INT NOT NULL,
            user_id BIGINT NOT NULL,
            amount_earned NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_log_share_daily (project_revenue_share_id, daily_revenue_id),
            INDEX IDX_A1B2C3D4E5F60718 (daily_revenue_id),
            INDEX IDX_A1B2C3D4A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE daily_revenue ADD CONSTRAINT FK_A29AC615166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_amount_history ADD CONSTRAINT FK_ADDF295166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_D7E8E7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_PRSHARE_INVESTISSEMENT FOREIGN KEY (investissement_id) REFERENCES investissement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_revenue_share ADD CONSTRAINT FK_D7E8E7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT FK_INV_LOG_SHARE FOREIGN KEY (project_revenue_share_id) REFERENCES project_revenue_share (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT FK_INV_LOG_DAILY FOREIGN KEY (daily_revenue_id) REFERENCES daily_revenue (revenue_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investor_revenue_log ADD CONSTRAINT FK_INV_LOG_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY FK_INV_LOG_SHARE');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY FK_INV_LOG_DAILY');
        $this->addSql('ALTER TABLE investor_revenue_log DROP FOREIGN KEY FK_INV_LOG_USER');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY FK_D7E8E7166D1F9C');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY FK_PRSHARE_INVESTISSEMENT');
        $this->addSql('ALTER TABLE project_revenue_share DROP FOREIGN KEY FK_D7E8E7A76ED395');
        $this->addSql('ALTER TABLE project_amount_history DROP FOREIGN KEY FK_ADDF295166D1F9C');
        $this->addSql('ALTER TABLE daily_revenue DROP FOREIGN KEY FK_A29AC615166D1F9C');

        $this->addSql('DROP TABLE investor_revenue_log');
        $this->addSql('DROP TABLE project_revenue_share');
        $this->addSql('DROP TABLE project_amount_history');
        $this->addSql('DROP TABLE daily_revenue');
    }
}
