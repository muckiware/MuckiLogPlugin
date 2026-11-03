<?php declare(strict_types=1);

namespace MuckiLogPlugin\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1793724000AdminChangeLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793724000;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `muwa_admin_change_log` (
              `id` BINARY(16) NOT NULL,
              `entity_name` VARCHAR(64) NOT NULL,
              `entity_id` VARCHAR(255) NOT NULL,
              `entity_label` VARCHAR(255) NULL,
              `action` VARCHAR(16) NOT NULL,
              `source_type` VARCHAR(16) NOT NULL,
              `user_id` BINARY(16) NULL,
              `user_name` VARCHAR(255) NULL,
              `integration_id` BINARY(16) NULL,
              `sales_channel_id` BINARY(16) NULL,
              `source_name` VARCHAR(255) NULL,
              `ip_address` VARCHAR(45) NULL,
              `changes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `idx_created_at` (`created_at`),
              KEY `idx_entity` (`entity_name`, `entity_id`),
              KEY `idx_user` (`user_id`),
              CONSTRAINT `fk.muwa_admin_change_log.user_id` FOREIGN KEY (`user_id`)
                REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
