<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2026 by muckiware
 *
 */
namespace MuckiLogPlugin\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1729614227 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729614227;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `muwa_logging_event` (
              `id` BINARY(16) NOT NULL,
              `vendor` VARCHAR(255) NULL,
              `plugin` VARCHAR(255) NULL,
              `loglevel` VARCHAR(255) NULL,
              `message` LONGTEXT NULL,
              `notification_email_template_id` binary(16) NOT NULL,
              `notification_email_receiver` varchar(255) NOT NULL,
              `notification_email_sender` varchar(255) DEFAULT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NOT NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
