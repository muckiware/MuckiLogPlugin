<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1729614227 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729614227;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `muwa_logging_event` (
              `id` BINARY(16) NOT NULL,
              `vendor` VARCHAR(255) NULL,
              `plugin` VARCHAR(255) NULL,
              `loglevel` VARCHAR(255) NULL,
              `message` LONGTEXT NULL,
              `created_at` DATETIME(3) NOT NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
