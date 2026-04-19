<?php
require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();

$sqls = [
    "DROP TABLE IF EXISTS message",
    "DROP TABLE IF EXISTS ticket",
    "CREATE TABLE ticket (id BIGINT AUTO_INCREMENT NOT NULL, user_id BIGINT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, dateCreation DATETIME DEFAULT NULL, priorite VARCHAR(255) DEFAULT NULL, statut VARCHAR(255) DEFAULT 'NOUVEAU', date_resolution DATETIME DEFAULT NULL, INDEX idx_ticket_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB",
    "CREATE TABLE message (Id BIGINT AUTO_INCREMENT NOT NULL, idTicket BIGINT NOT NULL, content LONGTEXT NOT NULL, sentAt DATETIME DEFAULT NULL, senderRole VARCHAR(10) NOT NULL, INDEX idx_msg_ticket (idTicket), PRIMARY KEY(Id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB",
    "ALTER TABLE ticket ADD CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE",
    "ALTER TABLE message ADD CONSTRAINT fk_message_ticket FOREIGN KEY (idTicket) REFERENCES ticket (id) ON DELETE CASCADE"
];

foreach ($sqls as $sql) {
    try {
        $conn->executeStatement($sql);
        echo "Executed OK: ". substr($sql, 0, 50) . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
