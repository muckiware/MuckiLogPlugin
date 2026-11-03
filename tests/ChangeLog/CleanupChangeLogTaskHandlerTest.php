<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use MuckiLogPlugin\Core\ConfigPath;
use MuckiLogPlugin\Schedules\CleanupChangeLogTask;
use MuckiLogPlugin\Schedules\CleanupChangeLogTaskHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CleanupChangeLogTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $this->connection->executeStatement('DELETE FROM muwa_admin_change_log');
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, true);
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_RETENTION_DAYS->value, 90);
    }

    public function testTaskDefinition(): void
    {
        static::assertSame('muwa.cleanup_change_log_task', CleanupChangeLogTask::getTaskName());
        static::assertSame(86400, CleanupChangeLogTask::getDefaultInterval());
    }

    public function testRunDeletesEntriesOlderThanRetention(): void
    {
        $this->insertRows([100, 50, 1]);

        $this->handler()->run();

        static::assertSame(2, $this->countRows());
    }

    public function testRetentionZeroKeepsEverything(): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_RETENTION_DAYS->value, 0);
        $this->insertRows([100, 50, 1]);

        $this->handler()->run();

        static::assertSame(3, $this->countRows());
    }

    public function testInactiveLogKeepsEverything(): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, false);
        $this->insertRows([100, 50, 1]);

        $this->handler()->run();

        static::assertSame(3, $this->countRows());
    }

    public function testDeleteOlderThanProcessesMultipleBlocks(): void
    {
        $this->insertRows(array_fill(0, 2500, 200));
        $this->insertRows([1]);

        $deleted = $this->handler()->deleteOlderThan(90);

        static::assertSame(2500, $deleted);
        static::assertSame(1, $this->countRows());
    }

    /**
     * @param list<int> $daysAgo
     */
    private function insertRows(array $daysAgo): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rows = [];
        $params = [];
        foreach ($daysAgo as $days) {
            $rows[] = '(?, ?, ?, ?, ?, ?)';
            $params[] = Uuid::randomBytes();
            $params[] = 'product';
            $params[] = Uuid::randomHex();
            $params[] = 'update';
            $params[] = 'system';
            $params[] = $now->modify("-{$days} days")->format('Y-m-d H:i:s.v');
        }

        foreach (array_chunk($rows, 500) as $i => $chunk) {
            $this->connection->executeStatement(
                'INSERT INTO muwa_admin_change_log (id, entity_name, entity_id, action, source_type, created_at) VALUES '
                . implode(',', $chunk),
                \array_slice($params, $i * 500 * 6, \count($chunk) * 6)
            );
        }
    }

    private function countRows(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM muwa_admin_change_log');
    }

    private function handler(): CleanupChangeLogTaskHandler
    {
        return new CleanupChangeLogTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            new ChangeLogConfig($this->systemConfig),
            $this->connection
        );
    }
}
